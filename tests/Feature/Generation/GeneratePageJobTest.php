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
            'status' => 'draft',
        ]);
        $pipeline = Mockery::mock(Pipeline::class);
        $pipeline->shouldReceive('generate')->once()->andThrow(new RuntimeException('Already recorded.'));

        (new GeneratePageJob($page->id))->handle($pipeline);

        $this->assertTrue(true);
    }
}
