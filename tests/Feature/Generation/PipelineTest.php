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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PipelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_pipeline_persists_marked_html_and_block_index(): void
    {
        [$project, $page] = $this->makePage('A developer tool landing page');

        $this->app->instance(LlmProvider::class, new FakeHtmlGenerationProvider($this->htmlArtifact()));

        $document = app(Pipeline::class)->generate($page);

        $page->refresh();

        $this->assertSame('valid', $page->status);
        $this->assertSame(2, $document['schema_version']);
        $this->assertStringContainsString('tw:block id="block_hero"', $page->html_source);
        $this->assertCount(2, $page->block_index);
        $this->assertSame('block_hero', $page->block_index[0]['id']);
        $this->assertSame($document, $page->document_json);
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
        $this->assertCount(3, $page->block_index);
        $this->assertSame('footer', $page->block_index[2]['type']);
        $this->assertDatabaseMissing('generation_events', [
            'page_id' => $page->id,
            'kind' => 'generation_failed',
            'stage' => 'pipeline',
        ]);
    }

    public function test_pipeline_rejects_unsafe_generated_html(): void
    {
        [$project, $page] = $this->makePage('Unsafe page');
        $artifact = $this->htmlArtifact();
        $artifact['raw_html'] = '<section><script>alert(1)</script></section>';

        $this->app->instance(LlmProvider::class, new FakeHtmlGenerationProvider($artifact));

        $this->expectExceptionMessage('script tags');

        app(Pipeline::class)->generate($page);
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
        $this->assertCount(1, $page->block_index);
        $this->assertSame('block_page', $page->block_index[0]['id']);
        $this->assertStringContainsString('data-tw-block="block_page"', $page->html_source);
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
            'document_json' => ['schema_version' => 2, 'page_metadata' => ['title' => 'Acme DevTools'], 'html_source' => $artifact['marked_html'], 'block_index' => $blockIndex, 'generation_history' => []],
            'html_source' => $artifact['marked_html'],
            'block_index' => $blockIndex,
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
        $this->assertCount(3, $page->block_index);
        $this->assertSame('block_hero', $page->block_index[0]['id']);
        $this->assertStringStartsWith('sec_', $page->block_index[1]['id']);
        $this->assertSame('block_features', $page->block_index[2]['id']);
        $this->assertStringContainsString('Editable block index', $page->block_index[2]['html']);
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
            'document_json' => ['schema_version' => 2, 'page_metadata' => ['title' => 'Acme DevTools'], 'html_source' => $artifact['marked_html'], 'block_index' => $blockIndex, 'generation_history' => []],
            'html_source' => $artifact['marked_html'],
            'block_index' => $blockIndex,
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
        $this->assertCount(1, $page->block_index);
        $this->assertSame('block_hero', $page->block_index[0]['id']);
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

    public function test_pipeline_streams_targeted_edit_html_source(): void
    {
        [$project, $page] = $this->makePage('A developer tool landing page');
        $artifact = $this->htmlArtifact();
        $blockIndex = app(BlockIndexer::class)->index($artifact['marked_html']);

        $page->forceFill([
            'document_json' => ['schema_version' => 2, 'page_metadata' => ['title' => 'Acme DevTools'], 'html_source' => $artifact['marked_html'], 'block_index' => $blockIndex, 'generation_history' => []],
            'html_source' => $artifact['marked_html'],
            'block_index' => $blockIndex,
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

        app(Pipeline::class)->edit($page, 'block_hero', 'Stream this edit into the modal.');

        $snapshot = app(GenerationStreamBuffer::class)->latestSectionSnapshot($page->id);

        $this->assertSame('targeted_edit', $snapshot['stage']);
        $this->assertStringContainsString('Streamed edit result', $snapshot['html']);
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
            'document_json' => ['schema_version' => 2, 'page_metadata' => [], 'html_source' => '', 'block_index' => [], 'generation_history' => []],
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
    public function __construct(private readonly array $artifact) {}

    public function sendStructured(StructuredRequest $request): StructuredResponse
    {
        $output = match ($request->stage) {
            'planner' => throw new \RuntimeException('Planner should not be called during page generation.'),
            'html_marker' => ['html_source' => $this->artifact['marked_html']],
            default => array_diff_key($this->artifact, ['marked_html' => true]),
        };

        return new StructuredResponse($request->stage, $request->model, $output);
    }
}

class FakeTargetedEditProvider implements LlmProvider
{
    public function __construct(private readonly string $replacement) {}

    public function sendStructured(StructuredRequest $request): StructuredResponse
    {
        return new StructuredResponse($request->stage, $request->model, [
            'html_source' => $this->replacement,
            'explanation' => 'Replaced the selected block with two focused sections.',
        ]);
    }
}

class FakeStreamingTargetedEditProvider extends FakeTargetedEditProvider
{
    public function __construct(private readonly string $streamedReplacement)
    {
        parent::__construct($streamedReplacement);
    }

    public function sendStructuredStream(StructuredRequest $request, callable $onPartialJson): StructuredResponse
    {
        $json = json_encode([
            'html_source' => $this->streamedReplacement,
            'explanation' => 'Streamed the selected edit.',
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        $onPartialJson(substr($json, 0, 90));
        $onPartialJson($json);

        return new StructuredResponse($request->stage, $request->model, [
            'html_source' => $this->streamedReplacement,
            'explanation' => 'Streamed the selected edit.',
        ]);
    }
}

class FakeRetrySectionProvider implements LlmProvider
{
    public function __construct(private readonly array $artifact) {}

    public function sendStructured(StructuredRequest $request): StructuredResponse
    {
        $output = match ($request->stage) {
            'planner' => throw new \RuntimeException('Planner should not be called during page generation.'),
            'section_generator' => array_merge(array_diff_key($this->artifact, ['marked_html' => true]), ['raw_html' => '']),
            'html_marker' => ['html_source' => $this->artifact['marked_html']],
            default => array_diff_key($this->artifact, ['marked_html' => true]),
        };

        return new StructuredResponse($request->stage, $request->model, $output);
    }
}

class FakeAlwaysEmptySectionProvider implements LlmProvider
{
    public function sendStructured(StructuredRequest $request): StructuredResponse
    {
        $output = match ($request->stage) {
            'planner' => throw new \RuntimeException('Planner should not be called during page generation.'),
            'html_marker' => ['html_source' => ''],
            default => [
                'title' => 'Recovery Page',
                'page_type' => 'landing',
                'goal' => 'Show a page even when generation returns empty output.',
                'audience' => 'Operators',
                'prompt_summary' => 'Recovery page',
                'raw_html' => '',
            ],
        };

        return new StructuredResponse($request->stage, $request->model, $output);
    }
}
