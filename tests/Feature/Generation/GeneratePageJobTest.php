<?php

namespace Tests\Feature\Generation;

use App\Jobs\GeneratePageJob;
use App\Jobs\GenerateRelatedPageJob;
use App\Models\Page;
use App\Models\Project;
use App\Services\Generation\Pipeline;
use App\Services\Generation\RelatedPagePromptBuilder;
use App\Services\Html\BlockIndexer;
use App\Services\Ids\IdGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class GeneratePageJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_does_not_bubble_pipeline_failures_into_sync_dispatch(): void
    {
        $project = Project::query()->create([
            'id' => app(IdGenerator::class)->project(),
            'name' => 'Acme',
        ]);
        $page = Page::query()->create([
            'id' => app(IdGenerator::class)->page(),
            'project_id' => $project->id,
            'name' => 'Homepage',
            'prompt' => '',
            'status' => 'draft',
        ]);
        $pipeline = Mockery::mock(Pipeline::class);
        $pipeline->shouldReceive('generate')->once()->andThrow(new RuntimeException('Already recorded.'));

        (new GeneratePageJob($page->id))->handle($pipeline);

        $this->assertTrue(true);
    }

    public function test_related_page_job_passes_source_context_as_prompt_override(): void
    {
        $project = Project::query()->create([
            'id' => app(IdGenerator::class)->project(),
            'name' => 'Acme',
        ]);
        $source = Page::query()->create([
            'id' => app(IdGenerator::class)->page(),
            'project_id' => $project->id,
            'name' => 'Homepage',
            'prompt' => 'Landing page',
            'html_source' => '<!-- tw:block id="block_header" type="header" label="Header" --><header>Acme nav</header><!-- /tw:block --><!-- tw:block id="block_hero" type="hero" label="Hero" --><section>Hero</section><!-- /tw:block -->',
            'status' => 'valid',
        ]);
        $target = Page::query()->create([
            'id' => app(IdGenerator::class)->page(),
            'project_id' => $project->id,
            'name' => 'Pricing',
            'prompt' => 'Pricing page',
            'status' => 'generating',
        ]);

        $pipeline = Mockery::mock(Pipeline::class);
        $pipeline->shouldReceive('generate')
            ->once()
            ->withArgs(function (Page $page, ?string $provider, ?string $model, ?string $apiKey, array $images, ?string $promptOverride) use ($target): bool {
                return $page->id === $target->id
                    && $provider === 'anthropic'
                    && $model === 'claude-test'
                    && $apiKey === 'key'
                    && $images === []
                    && is_string($promptOverride)
                    && str_contains($promptOverride, 'New page name: Pricing')
                    && str_contains($promptOverride, '<header>Acme nav</header>');
            });

        (new GenerateRelatedPageJob($source->id, $target->id, 'Pricing for teams.', 'anthropic', 'claude-test', 'key'))
            ->handle($pipeline, new RelatedPagePromptBuilder(new BlockIndexer));
    }
}
