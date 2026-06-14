<?php

namespace Tests\Feature;

use App\Jobs\GeneratePageScreenshotJob;
use App\Livewire\Projects\ProjectDashboard\ProjectDashboard;
use App\Models\Page;
use App\Models\Project;
use App\Models\User;
use App\Services\Ids\IdGenerator;
use App\Services\Rendering\Renderer;
use App\Services\Screenshots\PageScreenshotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class PageScreenshotTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->user->createDefaultTeam();
        $this->actingAs($this->user);
    }

    private function makeProject(): Project
    {
        return Project::query()->create([
            'id' => app(IdGenerator::class)->project(),
            'name' => 'Acme',
        ]);
    }

    private function makePage(Project $project, array $attributes = []): Page
    {
        return Page::query()->create(array_merge([
            'id' => app(IdGenerator::class)->page(),
            'project_id' => $project->id,
            'name' => 'Homepage',
            'prompt' => '',
            'html_source' => '<section><h1>Hello</h1></section>',
            'status' => 'valid',
        ], $attributes));
    }

    private function fakePngBytes(): string
    {
        $image = imagecreatetruecolor(8, 8);
        ob_start();
        imagepng($image);
        $bytes = (string) ob_get_clean();
        imagedestroy($image);

        return $bytes;
    }

    public function test_generate_screenshot_action_queues_job_and_marks_page_queued(): void
    {
        Queue::fake();

        $project = $this->makeProject();
        $page = $this->makePage($project);

        Livewire::test(ProjectDashboard::class, ['project' => $project])
            ->call('generateScreenshot', $page->id);

        $this->assertSame('queued', $page->refresh()->screenshot_status);

        Queue::assertPushed(
            GeneratePageScreenshotJob::class,
            fn (GeneratePageScreenshotJob $job): bool => $job->pageId === $page->id,
        );
    }

    public function test_generate_screenshot_action_ignores_pages_without_html(): void
    {
        Queue::fake();

        $project = $this->makeProject();
        $page = $this->makePage($project, ['html_source' => null, 'status' => 'draft']);

        Livewire::test(ProjectDashboard::class, ['project' => $project])
            ->call('generateScreenshot', $page->id);

        $this->assertNull($page->refresh()->screenshot_status);
        Queue::assertNotPushed(GeneratePageScreenshotJob::class);
    }

    public function test_job_marks_page_ready_after_capture(): void
    {
        Storage::fake('local');

        $project = $this->makeProject();
        $page = $this->makePage($project);

        $service = new class(app(Renderer::class)) extends PageScreenshotService
        {
            public function capture(Page $page): void
            {
                Storage::disk('local')->put('screenshots/'.$page->id.'.png', 'fake');

                $page->forceFill([
                    'screenshot_path' => 'screenshots/'.$page->id.'.png',
                    'screenshot_status' => 'ready',
                    'screenshot_taken_at' => now(),
                ])->save();
            }
        };

        (new GeneratePageScreenshotJob($page->id))->handle($service);

        $page->refresh();
        $this->assertTrue($page->hasScreenshot());
        $this->assertSame('screenshots/'.$page->id.'.png', $page->screenshot_path);
        Storage::disk('local')->assertExists($page->screenshot_path);
    }

    public function test_job_marks_page_failed_when_capture_throws(): void
    {
        $project = $this->makeProject();
        $page = $this->makePage($project);

        $service = new class(app(Renderer::class)) extends PageScreenshotService
        {
            public function capture(Page $page): void
            {
                throw new RuntimeException('chromium exploded');
            }
        };

        (new GeneratePageScreenshotJob($page->id))->handle($service);

        $this->assertSame('failed', $page->refresh()->screenshot_status);
    }

    public function test_controller_serves_stored_screenshot(): void
    {
        Storage::fake('local');

        $project = $this->makeProject();
        $page = $this->makePage($project, [
            'screenshot_path' => null,
            'screenshot_status' => 'ready',
        ]);

        $path = 'screenshots/'.$page->id.'.png';
        Storage::disk('local')->put($path, $this->fakePngBytes());
        $page->forceFill(['screenshot_path' => $path])->save();

        $this->get(route('builder.pages.screenshot', [$project, $page]))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
    }

    public function test_controller_returns_404_when_no_screenshot(): void
    {
        $project = $this->makeProject();
        $page = $this->makePage($project);

        $this->get(route('builder.pages.screenshot', [$project, $page]))
            ->assertNotFound();
    }

    public function test_controller_blocks_other_teams(): void
    {
        Storage::fake('local');

        $project = $this->makeProject();
        $page = $this->makePage($project, ['screenshot_status' => 'ready']);
        $path = 'screenshots/'.$page->id.'.png';
        Storage::disk('local')->put($path, $this->fakePngBytes());
        $page->forceFill(['screenshot_path' => $path])->save();

        $outsider = User::factory()->create();
        $outsider->createDefaultTeam();
        $this->actingAs($outsider);

        $this->get(route('builder.pages.screenshot', [$project, $page]))
            ->assertNotFound();
    }

    public function test_deleting_page_removes_screenshot_file(): void
    {
        Storage::fake('local');

        $project = $this->makeProject();
        $page = $this->makePage($project, ['screenshot_status' => 'ready']);
        $path = 'screenshots/'.$page->id.'.png';
        Storage::disk('local')->put($path, $this->fakePngBytes());
        $page->forceFill(['screenshot_path' => $path])->save();

        $page->delete();

        Storage::disk('local')->assertMissing($path);
    }
}
