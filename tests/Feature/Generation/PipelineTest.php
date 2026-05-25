<?php

namespace Tests\Feature\Generation;

use App\Models\Page;
use App\Models\Project;
use App\Services\Generation\GenerationStreamBuffer;
use App\Services\Generation\Pipeline;
use App\Services\Html\BlockIndexer;
use App\Services\Ids\IdGenerator;
use App\Services\Llm\LlmProvider;
use App\Services\Llm\StructuredRequest;
use App\Services\Llm\StructuredResponse;
use App\Services\Llm\TextRequest;
use App\Services\Llm\TextResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PipelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_pipeline_persists_marked_html_and_derives_block_index(): void
    {
        [$project, $page] = $this->makePage('A developer tool landing page');

        $this->app->instance(LlmProvider::class, new FakeHtmlGenerationProvider($this->htmlArtifact()));

        $document = app(Pipeline::class)->generate($page);

        $page->refresh();

        $this->assertSame('valid', $page->status);
        $this->assertSame(2, $document['schema_version']);
        $this->assertStringContainsString('tw:block id="block_hero"', $page->html_source);
        $blockIndex = app(BlockIndexer::class)->index($page->html_source);
        $this->assertCount(2, $blockIndex);
        $this->assertSame('block_hero', $blockIndex[0]['id']);
        $this->assertDatabaseHas('generation_events', [
            'page_id' => $page->id,
            'kind' => 'generation_completed',
            'stage' => 'validation',
            'level' => 'success',
        ]);
    }

    public function test_pipeline_accepts_freeform_footer_without_schema_column_counts(): void
    {
        [$project, $page] = $this->makePage('A cat nail studio landing page');
        $artifact = $this->htmlArtifact();
        $artifact['raw_html'] .= "\n".<<<'HTML'
<footer class="bg-neutral-950 px-6 py-12 text-white">
  <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-6">
    <p>Cat nail studio</p>
    <nav class="flex gap-4"><a href="#hero">Home</a><a href="#book">Book</a></nav>
  </div>
</footer>
HTML;
        $artifact['marked_html'] .= "\n".$this->footerBlock();

        $this->app->instance(LlmProvider::class, new FakeHtmlGenerationProvider($artifact));

        app(Pipeline::class)->generate($page);

        $page->refresh();

        $this->assertSame('valid', $page->status);
        $blockIndex = app(BlockIndexer::class)->index($page->html_source);
        $this->assertCount(3, $blockIndex);
        $this->assertSame('footer', $blockIndex[2]['type']);
        $this->assertDatabaseMissing('generation_events', [
            'page_id' => $page->id,
            'kind' => 'generation_failed',
            'stage' => 'pipeline',
        ]);
    }

    public function test_pipeline_scrubs_malformed_utf8_before_html_persistence(): void
    {
        [$project, $page] = $this->makePage('A page with malformed bytes');
        $artifact = $this->htmlArtifact();
        $malformed = 'Broken '.chr(195).'(';

        $artifact['title'] = 'Acme '.chr(195).'(';
        $artifact['raw_html'] = '<section><p>'.$malformed.'</p></section>';
        $artifact['marked_html'] = '<!-- tw:block id="block_hero" type="hero" label="Hero" --><section data-node-id="block_hero" data-node-type="hero" data-tw-block="block_hero"><p>'.$malformed.'</p></section><!-- /tw:block -->';

        $this->app->instance(LlmProvider::class, new FakeHtmlGenerationProvider($artifact));

        app(Pipeline::class)->generate($page);

        $page->refresh();

        $this->assertSame('valid', $page->status);
        $this->assertTrue(mb_check_encoding($page->html_source, 'UTF-8'));
        $this->assertStringNotContainsString(chr(195).'(', $page->html_source);
    }

    public function test_pipeline_derives_long_multibyte_block_summaries_as_valid_utf8(): void
    {
        [$project, $page] = $this->makePage('A page with a long multibyte summary');
        $artifact = $this->htmlArtifact();
        $text = str_repeat('a', 156).'€'.str_repeat('b', 20);
        $artifact['raw_html'] = '<section><p>'.$text.'</p></section>';
        $artifact['marked_html'] = '<!-- tw:block id="block_hero" type="hero" label="Hero" --><section data-node-id="block_hero" data-node-type="hero" data-tw-block="block_hero"><p>'.$text.'</p></section><!-- /tw:block -->';

        $this->app->instance(LlmProvider::class, new FakeHtmlGenerationProvider($artifact));

        app(Pipeline::class)->generate($page);

        $page->refresh();

        $blockIndex = app(BlockIndexer::class)->index($page->html_source);
        $this->assertTrue(mb_check_encoding($blockIndex[0]['summary'], 'UTF-8'));
        $this->assertStringContainsString('€', $blockIndex[0]['summary']);
    }

    public function test_pipeline_snapshots_a_version_after_each_generation_and_edit(): void
    {
        [$project, $page] = $this->makePage('A versioned landing page');

        $this->app->instance(LlmProvider::class, new FakeHtmlGenerationProvider($this->htmlArtifact()));

        app(Pipeline::class)->generate($page);

        $page->refresh();
        $this->assertCount(1, $page->versions()->get());
        $generationVersion = $page->versions()->orderByDesc('created_at')->first();
        $this->assertSame('generation', $generationVersion->created_by_kind);
        $this->assertStringContainsString('A versioned landing page', (string) $generationVersion->summary);
        $this->assertStringContainsString('tw:block id="block_hero"', (string) $generationVersion->html_source);

        $replacement = <<<'HTML'
<!-- tw:block id="draft_story" type="story" label="Story" -->
<section class="bg-white px-6 py-20"><h2>Edited story</h2></section>
<!-- /tw:block -->
HTML;
        $this->app->instance(LlmProvider::class, new FakeTargetedEditProvider($replacement));

        app(Pipeline::class)->edit($page, 'block_hero', 'Replace the hero with an edited story.');

        $page->refresh();
        $this->assertCount(2, $page->versions()->get());
        $editVersion = $page->versions()->orderByDesc('created_at')->first();
        $this->assertSame('edit', $editVersion->created_by_kind);
        $this->assertStringContainsString('Replace the hero with an edited story', (string) $editVersion->summary);
        $this->assertStringContainsString('Edited story', (string) $editVersion->html_source);
    }

    public function test_pipeline_rejects_inline_script_tags(): void
    {
        [$project, $page] = $this->makePage('Unsafe page');
        $artifact = $this->htmlArtifact();
        $artifact['raw_html'] = '<section><script>alert(1)</script></section>';

        $this->app->instance(LlmProvider::class, new FakeHtmlGenerationProvider($artifact));

        $this->expectExceptionMessage('script tag');

        app(Pipeline::class)->generate($page);
    }

    public function test_pipeline_accepts_external_https_script_tags(): void
    {
        [$project, $page] = $this->makePage('Page with external scripts');
        $artifact = $this->htmlArtifact();
        $artifact['raw_html'] = '<!-- tw:block id="block_hero" type="hero" label="Hero" -->'
            .'<section><script src="https://unpkg.com/htmx.org@1.9.10"></script>'
            .'<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>'
            .'<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter">'
            .'<h1>Hello</h1></section><!-- /tw:block -->';
        $artifact['marked_html'] = $artifact['raw_html'];

        $this->app->instance(LlmProvider::class, new FakeHtmlGenerationProvider($artifact));

        app(Pipeline::class)->generate($page);

        $page->refresh();

        $this->assertSame('valid', $page->status);
        $this->assertStringContainsString('https://unpkg.com/htmx.org', $page->html_source);
        $this->assertStringContainsString('https://cdn.jsdelivr.net/npm/swiper', $page->html_source);
    }

    public function test_pipeline_sanitizes_recoverable_inline_handlers_from_generated_html(): void
    {
        [$project, $page] = $this->makePage('Signup page');
        $artifact = $this->htmlArtifact();
        $artifact['raw_html'] = '<section><form onsubmit="return false;"><button onclick="alert(1)">Join</button></form></section>';
        $artifact['marked_html'] = '<!-- tw:block id="block_form" type="form" label="Form" --><section data-node-id="block_form" data-node-type="form" data-tw-block="block_form"><form onsubmit="return false;"><button onclick="alert(1)">Join</button></form></section><!-- /tw:block -->';

        $this->app->instance(LlmProvider::class, new FakeHtmlGenerationProvider($artifact));

        app(Pipeline::class)->generate($page);

        $page->refresh();

        $this->assertSame('valid', $page->status);
        $this->assertStringNotContainsString('onsubmit=', $page->html_source);
        $this->assertStringNotContainsString('onclick=', $page->html_source);
        $this->assertStringContainsString('<button>Join</button>', $page->html_source);
    }

    public function test_pipeline_retries_empty_section_generator_output(): void
    {
        [$project, $page] = $this->makePage('Retry section draft');
        $artifact = $this->htmlArtifact();

        $this->app->instance(LlmProvider::class, new FakeRetrySectionProvider($artifact));

        app(Pipeline::class)->generate($page);

        $page->refresh();

        $this->assertSame('valid', $page->status);
        $this->assertDatabaseHas('generation_events', [
            'page_id' => $page->id,
            'kind' => 'stage_completed',
            'stage' => 'section_generator',
            'level' => 'warning',
        ]);
    }

    public function test_pipeline_creates_fallback_draft_when_section_generator_stays_empty(): void
    {
        [$project, $page] = $this->makePage('Empty section draft');

        $this->app->instance(LlmProvider::class, new FakeAlwaysEmptySectionProvider);

        app(Pipeline::class)->generate($page);

        $page->refresh();

        $this->assertSame('valid', $page->status);
        $this->assertNotSame('', $page->html_source);
        $this->assertStringContainsString('AI recovery draft', $page->html_source);
        $this->assertDatabaseHas('generation_events', [
            'page_id' => $page->id,
            'kind' => 'stage_completed',
            'stage' => 'section_generator',
            'level' => 'warning',
        ]);

        $this->assertDatabaseHas('generation_events', [
            'page_id' => $page->id,
            'kind' => 'stage_completed',
            'stage' => 'html_marker',
            'level' => 'success',
        ]);
    }

    public function test_pipeline_wraps_raw_html_when_marker_output_is_empty(): void
    {
        [$project, $page] = $this->makePage('Marker fallback');
        $artifact = $this->htmlArtifact();
        $artifact['raw_html'] = 'Plain text without a block wrapper.';
        $artifact['marked_html'] = '';

        $this->app->instance(LlmProvider::class, new FakeHtmlGenerationProvider($artifact));

        app(Pipeline::class)->generate($page);

        $page->refresh();

        $this->assertSame('valid', $page->status);
        $blockIndex = app(BlockIndexer::class)->index($page->html_source);
        $this->assertCount(1, $blockIndex);
        $this->assertSame('block_page', $blockIndex[0]['id']);
        $this->assertStringNotContainsString('data-tw-block="block_page"', $page->html_source);
        $this->assertDatabaseHas('generation_events', [
            'page_id' => $page->id,
            'kind' => 'stage_completed',
            'stage' => 'html_marker',
            'level' => 'warning',
        ]);
    }

    public function test_pipeline_targeted_edit_can_replace_one_block_with_multiple_blocks(): void
    {
        [$project, $page] = $this->makePage('A developer tool landing page');
        $artifact = $this->htmlArtifact();
        $blockIndex = app(BlockIndexer::class)->index($artifact['marked_html']);

        $page->forceFill([
            'html_source' => $artifact['marked_html'],
            'status' => 'valid',
        ])->save();

        $replacement = <<<'HTML'
<!-- tw:block id="draft_about" type="content" label="About" -->
<section data-node-id="draft_about" data-node-type="content" data-tw-block="draft_about" class="bg-white px-6 py-20">
  <div class="mx-auto max-w-4xl"><h2 class="text-4xl font-bold">Built for calm launches</h2></div>
</section>
<!-- /tw:block -->
<!-- tw:block id="draft_proof" type="proof" label="Proof" -->
<section data-node-id="draft_proof" data-node-type="proof" data-tw-block="draft_proof" class="bg-cyan-50 px-6 py-16">
  <div class="mx-auto grid max-w-5xl gap-6 md:grid-cols-3"><p>Fast drafts</p><p>Clean markers</p><p>Focused edits</p></div>
</section>
<!-- /tw:block -->
HTML;

        $this->app->instance(LlmProvider::class, new FakeTargetedEditProvider($replacement));

        app(Pipeline::class)->edit($page, 'block_hero', 'Remove this hero and add an about section plus a proof section.');

        $page->refresh();

        $this->assertSame('valid', $page->status);
        $blockIndex = app(BlockIndexer::class)->index($page->html_source);
        $this->assertCount(3, $blockIndex);
        $this->assertSame('block_hero', $blockIndex[0]['id']);
        $this->assertStringStartsWith('sec_', $blockIndex[1]['id']);
        $this->assertSame('block_features', $blockIndex[2]['id']);
        $this->assertStringContainsString('Editable block index', $blockIndex[2]['html']);
        $this->assertDatabaseHas('generation_events', [
            'page_id' => $page->id,
            'kind' => 'edit_applied',
            'stage' => 'targeted_edit',
            'level' => 'success',
        ]);
    }

    public function test_pipeline_targeted_edit_can_replace_multiple_blocks_with_one_block(): void
    {
        [$project, $page] = $this->makePage('A developer tool landing page');
        $artifact = $this->htmlArtifact();
        $blockIndex = app(BlockIndexer::class)->index($artifact['marked_html']);

        $page->forceFill([
            'html_source' => $artifact['marked_html'],
            'status' => 'valid',
        ])->save();

        $replacement = <<<'HTML'
<!-- tw:block id="draft_story" type="story" label="Story" -->
<section data-node-id="draft_story" data-node-type="story" data-tw-block="draft_story" class="bg-white px-6 py-24">
  <div class="mx-auto max-w-4xl"><h2 class="text-4xl font-bold">One focused product story</h2><p>Everything from the original hero and feature grid is now a single narrative.</p></div>
</section>
<!-- /tw:block -->
HTML;

        $this->app->instance(LlmProvider::class, new FakeTargetedEditProvider($replacement));

        app(Pipeline::class)->editMany($page, ['block_hero', 'block_features'], 'Replace these sections with one focused product story.');

        $page->refresh();

        $this->assertSame('valid', $page->status);
        $blockIndex = app(BlockIndexer::class)->index($page->html_source);
        $this->assertCount(1, $blockIndex);
        $this->assertSame('block_hero', $blockIndex[0]['id']);
        $this->assertStringContainsString('One focused product story', $page->html_source);
        $this->assertStringNotContainsString('Editable block index', $page->html_source);
        $this->assertDatabaseHas('generation_events', [
            'page_id' => $page->id,
            'kind' => 'edit_applied',
            'stage' => 'targeted_edit',
            'level' => 'success',
            'target_id' => 'block_hero,block_features',
        ]);
    }

    public function test_pipeline_publishes_final_targeted_edit_html_when_provider_does_not_stream(): void
    {
        [$project, $page] = $this->makePage('A developer tool landing page');
        $artifact = $this->htmlArtifact();

        $page->forceFill([
            'html_source' => $artifact['marked_html'],
            'status' => 'valid',
        ])->save();

        $replacement = <<<'HTML'
<!-- tw:block id="draft_story" type="story" label="Story" -->
<section data-node-id="draft_story" data-node-type="story" data-tw-block="draft_story" class="bg-white px-6 py-24">
  <div class="mx-auto max-w-4xl"><h2 class="text-4xl font-bold">Final edit result</h2></div>
</section>
<!-- /tw:block -->
HTML;

        $this->app->instance(LlmProvider::class, new FakeTargetedEditProvider($replacement));

        app(Pipeline::class)->edit($page, 'block_hero', 'Publish this edit into the inspector stream.');

        $snapshot = app(GenerationStreamBuffer::class)->latestSectionSnapshot($page->id);

        $this->assertSame('targeted_edit', $snapshot['stage']);
        $this->assertStringContainsString('Final edit result', $snapshot['html']);
    }

    public function test_pipeline_inserts_a_new_section_after_an_anchor_block(): void
    {
        [$project, $page] = $this->makePage('A developer tool landing page');
        $artifact = $this->htmlArtifact();

        $page->forceFill([
            'html_source' => $artifact['marked_html'],
            'status' => 'valid',
        ])->save();

        $inserted = <<<'HTML'
<!-- tw:block id="block_logos" type="logo_cloud" label="Logos" -->
<section class="bg-neutral-50 px-6 py-12">
  <div class="mx-auto max-w-5xl"><p>Trusted by careful teams</p></div>
</section>
<!-- /tw:block -->
HTML;

        $this->app->instance(LlmProvider::class, new FakeTargetedEditProvider($inserted));

        app(Pipeline::class)->insertSection($page, 'block_hero', 'after', 'Add a compact customer logo band.');

        $page->refresh();

        $this->assertSame('valid', $page->status);
        $blockIndex = app(BlockIndexer::class)->index($page->html_source);
        $this->assertCount(3, $blockIndex);
        $this->assertSame('block_hero', $blockIndex[0]['id']);
        $this->assertSame('block_logos', $blockIndex[1]['id']);
        $this->assertSame('block_features', $blockIndex[2]['id']);
        $this->assertStringContainsString('Trusted by careful teams', $page->html_source);
        $this->assertDatabaseHas('generation_events', [
            'page_id' => $page->id,
            'kind' => 'insert_applied',
            'stage' => 'section_inserter',
            'level' => 'success',
            'target_id' => 'block_hero',
        ]);

        $snapshot = app(GenerationStreamBuffer::class)->latestSectionSnapshot($page->id);
        $this->assertSame('section_inserter', $snapshot['stage']);
        $this->assertStringContainsString('Trusted by careful teams', $snapshot['html']);
    }

    public function test_pipeline_streams_targeted_edit_html_source(): void
    {
        [$project, $page] = $this->makePage('A developer tool landing page');
        $artifact = $this->htmlArtifact();
        $blockIndex = app(BlockIndexer::class)->index($artifact['marked_html']);

        $page->forceFill([
            'html_source' => $artifact['marked_html'],
            'status' => 'valid',
        ])->save();

        $replacement = <<<'HTML'
<!-- tw:block id="draft_story" type="story" label="Story" -->
<section data-node-id="draft_story" data-node-type="story" data-tw-block="draft_story" class="bg-white px-6 py-24">
  <div class="mx-auto max-w-4xl"><h2 class="text-4xl font-bold">Streamed edit result</h2></div>
</section>
<!-- /tw:block -->
HTML;

        $this->app->instance(LlmProvider::class, new FakeStreamingTargetedEditProvider($replacement));

        app(Pipeline::class)->edit($page, 'block_hero', 'Stream this edit into the inspector.');

        $snapshot = app(GenerationStreamBuffer::class)->latestSectionSnapshot($page->id);

        $this->assertSame('targeted_edit', $snapshot['stage']);
        $this->assertStringContainsString('Streamed edit result', $snapshot['html']);
    }

    public function test_pipeline_enhances_document_without_nesting_markers(): void
    {
        [$project, $page] = $this->makePage('A developer tool landing page');

        $page->forceFill([
            'html_source' => <<<'HTML'
<!-- tw:block id="block_hero" type="hero" label="Hero" -->
<section class="bg-neutral-950 px-6 py-24 text-white"><h1>Ship pages with marked blocks</h1></section>
<!-- /tw:block -->
<!-- tw:block id="block_testimonials" type="testimonials" label="Testimonials" -->
<section class="bg-white px-6 py-20">
  <div class="mx-auto grid max-w-6xl gap-6 md:grid-cols-2">
    <article class="rounded-2xl border border-neutral-200 p-6">"Fast drafts" - Ana</article>
    <article class="rounded-2xl border border-neutral-200 p-6">"Clean markers" - Bo</article>
  </div>
</section>
<!-- /tw:block -->
HTML,
            'status' => 'valid',
        ])->save();

        $granularized = <<<'HTML'
<!-- tw:block id="block_hero" type="hero" label="Hero" -->
<section class="bg-neutral-950 px-6 py-24 text-white"><h1>Ship pages with marked blocks</h1></section>
<!-- /tw:block -->
<section class="bg-white px-6 py-20">
  <div class="mx-auto grid max-w-6xl gap-6 md:grid-cols-2">
    <!-- tw:block id="draft_testimonial_ana" type="testimonial" label="Testimonial - Ana" -->
    <article class="rounded-2xl border border-neutral-200 p-6">"Fast drafts" - Ana</article>
    <!-- /tw:block -->
    <!-- tw:block id="draft_testimonial_bo" type="testimonial" label="Testimonial - Bo" -->
    <article class="rounded-2xl border border-neutral-200 p-6">"Clean markers" - Bo</article>
    <!-- /tw:block -->
  </div>
</section>
HTML;
        $provider = new FakeTargetedEditProvider($granularized);

        $this->app->instance(LlmProvider::class, $provider);

        app(Pipeline::class)->enhanceDocument(
            $page,
            'Add more granular editable tw:block regions around repeated testimonial cards.',
            'Refined editable block markers.',
        );

        $page->refresh();

        $this->assertSame('valid', $page->status);
        $blockIndex = app(BlockIndexer::class)->index($page->html_source);
        $this->assertCount(3, $blockIndex);
        $this->assertSame('block_hero', $blockIndex[0]['id']);
        $this->assertStringStartsWith('sec_', $blockIndex[1]['id']);
        $this->assertStringStartsWith('sec_', $blockIndex[2]['id']);
        $this->assertStringNotContainsString('block_testimonials', $page->html_source);
        $this->assertStringContainsString('Enhancement request', (string) $provider->lastTextRequest?->userPrompt);
        $this->assertStringContainsString('Current complete HTML document', (string) $provider->lastTextRequest?->userPrompt);
        $this->assertDatabaseHas('generation_events', [
            'page_id' => $page->id,
            'kind' => 'enhance_applied',
            'stage' => 'document_enhancer',
            'level' => 'success',
        ]);
        $this->assertCount(1, $page->versions()->get());
    }

    public function test_pipeline_rejects_enhanced_html_with_nested_blocks(): void
    {
        [$project, $page] = $this->makePage('A developer tool landing page');

        $page->forceFill([
            'html_source' => $this->htmlArtifact()['marked_html'],
            'status' => 'valid',
        ])->save();

        $nested = <<<'HTML'
<!-- tw:block id="block_outer" type="section" label="Outer" -->
<section>
  <!-- tw:block id="block_inner" type="card" label="Inner" -->
  <article>Nested marker</article>
  <!-- /tw:block -->
</section>
<!-- /tw:block -->
HTML;

        $this->app->instance(LlmProvider::class, new FakeTargetedEditProvider($nested));

        $this->expectExceptionMessage('Block markers must not be nested.');

        app(Pipeline::class)->enhanceDocument($page, 'Add more granular editable blocks.', 'Refined editable block markers.');
    }

    public function test_pipeline_forwards_reference_images_to_full_generation(): void
    {
        [$project, $page] = $this->makePage('Recreate this homepage screenshot');
        $provider = new FakeHtmlGenerationProvider($this->htmlArtifact());

        $this->app->instance(LlmProvider::class, $provider);

        $images = [['base64' => base64_encode('screenshot'), 'mime_type' => 'image/png']];

        app(Pipeline::class)->generate($page, images: $images);

        $this->assertSame($images, $provider->lastTextRequest?->images);
        $this->assertStringContainsString('visual reference screenshot', (string) $provider->lastTextRequest?->userPrompt);
    }

    public function test_pipeline_forwards_reference_images_to_targeted_edits(): void
    {
        [$project, $page] = $this->makePage('A developer tool landing page');
        $artifact = $this->htmlArtifact();

        $page->forceFill([
            'html_source' => $artifact['marked_html'],
            'status' => 'valid',
        ])->save();

        $replacement = <<<'HTML'
<!-- tw:block id="draft_story" type="story" label="Story" -->
<section class="bg-white px-6 py-24"><h2>Image guided edit</h2></section>
<!-- /tw:block -->
HTML;
        $provider = new FakeTargetedEditProvider($replacement);

        $this->app->instance(LlmProvider::class, $provider);

        $images = [['base64' => base64_encode('screenshot'), 'mime_type' => 'image/webp']];

        app(Pipeline::class)->edit($page, 'block_hero', 'Match the reference screenshot.', images: $images);

        $this->assertSame($images, $provider->lastTextRequest?->images);
        $this->assertStringContainsString('visual reference screenshot', (string) $provider->lastTextRequest?->userPrompt);
    }

    private function makePage(string $prompt): array
    {
        $ids = app(IdGenerator::class);
        $project = Project::query()->create([
            'id' => $ids->project(),
            'name' => 'Acme',
        ]);
        $page = Page::query()->create([
            'id' => $ids->page(),
            'project_id' => $project->id,
            'name' => 'Homepage',
            'prompt' => $prompt,
            'status' => 'draft',
        ]);

        return [$project, $page];
    }

    private function htmlArtifact(): array
    {
        return [
            'title' => 'Acme DevTools',
            'page_type' => 'landing',
            'goal' => 'Convince developers to try Acme.',
            'audience' => 'Senior backend engineers',
            'prompt_summary' => 'Developer tool landing page',
            'raw_html' => <<<'HTML'
<section class="bg-neutral-950 px-6 py-24 text-white">
  <div class="mx-auto max-w-5xl">
    <p class="text-sm font-semibold text-cyan-300">Structured HTML, full design freedom</p>
    <h1 class="mt-4 text-5xl font-bold">Ship pages with marked blocks</h1>
  </div>
</section>
<section class="bg-white px-6 py-20">
  <div class="mx-auto grid max-w-6xl gap-6 md:grid-cols-3">
    <article class="rounded-2xl border border-neutral-200 p-6">Editable block index</article>
    <article class="rounded-2xl border border-neutral-200 p-6">Freeform Tailwind HTML</article>
    <article class="rounded-2xl border border-neutral-200 p-6">Targeted replacement ready</article>
  </div>
</section>
HTML,
            'marked_html' => <<<'HTML'
<!-- tw:block id="block_hero" type="hero" label="Hero" -->
<section data-node-id="block_hero" data-node-type="hero" data-tw-block="block_hero" class="bg-neutral-950 px-6 py-24 text-white">
  <div class="mx-auto max-w-5xl">
    <p class="text-sm font-semibold text-cyan-300">Structured HTML, full design freedom</p>
    <h1 class="mt-4 text-5xl font-bold">Ship pages with marked blocks</h1>
  </div>
</section>
<!-- /tw:block -->
<!-- tw:block id="block_features" type="features" label="Features" -->
<section data-node-id="block_features" data-node-type="features" data-tw-block="block_features" class="bg-white px-6 py-20">
  <div class="mx-auto grid max-w-6xl gap-6 md:grid-cols-3">
    <article class="rounded-2xl border border-neutral-200 p-6">Editable block index</article>
    <article class="rounded-2xl border border-neutral-200 p-6">Freeform Tailwind HTML</article>
    <article class="rounded-2xl border border-neutral-200 p-6">Targeted replacement ready</article>
  </div>
</section>
<!-- /tw:block -->
HTML,
        ];
    }

    private function footerBlock(): string
    {
        return <<<'HTML'
<!-- tw:block id="block_footer" type="footer" label="Footer" -->
<footer data-node-id="block_footer" data-node-type="footer" data-tw-block="block_footer" class="bg-neutral-950 px-6 py-12 text-white">
  <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-6">
    <p>Cat nail studio</p>
    <nav class="flex gap-4"><a href="#hero">Home</a><a href="#book">Book</a></nav>
  </div>
</footer>
<!-- /tw:block -->
HTML;
    }
}

class FakeHtmlGenerationProvider implements LlmProvider
{
    public ?TextRequest $lastTextRequest = null;

    public function __construct(private readonly array $artifact) {}

    public function sendTextStream(TextRequest $request, callable $onDelta): TextResponse
    {
        $this->lastTextRequest = $request;
        $html = (string) ($this->artifact['raw_html'] ?? '');
        $onDelta($html, 0);

        return new TextResponse($request->stage, $request->model, $html);
    }

    public function sendStructured(StructuredRequest $request): StructuredResponse
    {
        $output = match ($request->stage) {
            'html_marker' => ['html_source' => $this->artifact['marked_html']],
            default => throw new \RuntimeException("Unexpected structured request [{$request->stage}]."),
        };

        return new StructuredResponse($request->stage, $request->model, $output);
    }
}

class FakeTargetedEditProvider implements LlmProvider
{
    public ?TextRequest $lastTextRequest = null;

    public function __construct(private readonly string $replacement) {}

    public function sendTextStream(TextRequest $request, callable $onDelta): TextResponse
    {
        $this->lastTextRequest = $request;
        $onDelta($this->replacement, 0);

        return new TextResponse($request->stage, $request->model, $this->replacement);
    }

    public function sendStructured(StructuredRequest $request): StructuredResponse
    {
        throw new \RuntimeException("Unexpected structured request [{$request->stage}].");
    }
}

class FakeStreamingTargetedEditProvider extends FakeTargetedEditProvider
{
    public function __construct(private readonly string $streamedReplacement)
    {
        parent::__construct($streamedReplacement);
    }

    public function sendTextStream(TextRequest $request, callable $onDelta): TextResponse
    {
        $half = (int) floor(strlen($this->streamedReplacement) / 2);
        $firstChunk = substr($this->streamedReplacement, 0, $half);
        $secondChunk = substr($this->streamedReplacement, $half);

        $onDelta($firstChunk, 0);
        $onDelta($secondChunk, strlen($firstChunk));

        return new TextResponse($request->stage, $request->model, $this->streamedReplacement);
    }
}

class FakeRetrySectionProvider implements LlmProvider
{
    public function __construct(private readonly array $artifact) {}

    public function sendTextStream(TextRequest $request, callable $onDelta): TextResponse
    {
        $html = $request->stage === 'section_generator'
            ? ''
            : (string) ($this->artifact['raw_html'] ?? '');

        if ($html !== '') {
            $onDelta($html, 0);
        }

        return new TextResponse($request->stage, $request->model, $html);
    }

    public function sendStructured(StructuredRequest $request): StructuredResponse
    {
        $output = match ($request->stage) {
            'html_marker' => ['html_source' => $this->artifact['marked_html']],
            default => throw new \RuntimeException("Unexpected structured request [{$request->stage}]."),
        };

        return new StructuredResponse($request->stage, $request->model, $output);
    }
}

class FakeAlwaysEmptySectionProvider implements LlmProvider
{
    public function sendTextStream(TextRequest $request, callable $onDelta): TextResponse
    {
        return new TextResponse($request->stage, $request->model, '');
    }

    public function sendStructured(StructuredRequest $request): StructuredResponse
    {
        $output = match ($request->stage) {
            'html_marker' => ['html_source' => ''],
            default => throw new \RuntimeException("Unexpected structured request [{$request->stage}]."),
        };

        return new StructuredResponse($request->stage, $request->model, $output);
    }
}
