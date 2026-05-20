<?php

namespace Tests\Feature\Generation;

use App\Jobs\GeneratePageJob;
use App\Models\Page;
use App\Models\Project;
use App\Services\Generation\Pipeline;
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
            'document_json' => $this->emptyDocument(),
            'status' => 'draft',
        ]);
        $pipeline = Mockery::mock(Pipeline::class);
        $pipeline->shouldReceive('generate')->once()->andThrow(new RuntimeException('Already recorded.'));

        (new GeneratePageJob($page->id))->handle($pipeline);

        $this->assertTrue(true);
    }

    private function emptyDocument(): array
    {
        $now = now('UTC')->format('Y-m-d\TH:i:s\Z');

        return [
            'schema_version' => 1,
            'page_metadata' => [
                'title' => 'Homepage',
                'page_type' => 'landing',
                'goal' => 'Draft a landing page.',
                'audience' => 'General audience',
                'prompt_summary' => 'Empty draft page',
                'status' => 'draft',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            'design_system' => [
                'colors' => [
                    'primary' => 'cyan',
                    'accent' => 'emerald',
                    'neutral' => 'neutral',
                    'background' => 'white',
                    'foreground' => 'neutral-950',
                ],
                'typography' => [
                    'heading_family' => 'sans',
                    'body_family' => 'sans',
                    'scale' => 'comfortable',
                ],
                'spacing' => [
                    'density' => 'comfortable',
                    'section_padding' => 'md',
                ],
                'radius' => 'md',
                'tone' => 'professional',
                'dark_mode' => false,
            ],
            'document_tree' => [],
            'generation_history' => [],
        ];
    }
}
