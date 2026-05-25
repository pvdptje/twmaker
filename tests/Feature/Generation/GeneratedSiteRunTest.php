<?php

namespace Tests\Feature\Generation;

use App\Jobs\GenerateSiteRunJob;
use App\Models\Page;
use App\Models\Project;
use App\Models\SiteGenerationRun;
use App\Models\SiteGenerationRunPage;
use App\Models\Team;
use App\Models\User;
use App\Services\Generation\GenerationEventRecorder;
use App\Services\Generation\Pipeline;
use App\Services\Generation\RelatedPagePromptBuilder;
use App\Services\Generation\SitePagePlanner;
use App\Services\Generation\SiteZipFinalizer;
use App\Services\Ids\IdGenerator;
use App\Services\Llm\LlmProvider;
use App\Services\Llm\StructuredRequest;
use App\Services\Llm\StructuredResponse;
use App\Services\Llm\TeamProviderCredentials;
use App\Services\Llm\TextRequest;
use App\Services\Llm\TextResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GeneratedSiteRunTest extends TestCase
{
    use RefreshDatabase;

    public function test_site_page_planner_sends_source_menu_and_normalizes_pages(): void
    {
        $project = $this->project();
        $source = $this->page($project, [
            'name' => 'Homepage',
            'prompt' => 'A launch site',
            'html_source' => '<nav><a href="/pricing">Pricing</a><a href="/contact">Contact</a></nav><main>Acme</main>',
            'status' => 'valid',
        ]);
        $provider = new FakePlannerProvider([
            'summary' => 'Two pages from the nav.',
            'pages' => [
                [
                    'name' => 'Pricing',
                    'slug' => 'pricing',
                    'brief' => 'Create pricing for Acme.',
                    'source' => 'menu',
                    'source_label' => 'Pricing',
                    'reason' => 'It is in the nav.',
                    'confidence' => 0.9,
                ],
                [
                    'name' => 'Contact',
                    'slug' => 'pricing',
                    'brief' => 'Create contact options.',
                    'source' => 'menu',
                    'source_label' => 'Contact',
                    'reason' => 'It is in the nav.',
                    'confidence' => 1.4,
                ],
            ],
        ]);
        $this->app->instance(LlmProvider::class, $provider);

        $plan = app(SitePagePlanner::class)->plan($source, $project, 'anthropic', 'claude-test', 'key');

        $this->assertSame('site_page_planner', $provider->request?->stage);
        $this->assertStringContainsString('Pricing', $provider->request?->userPrompt ?? '');
        $this->assertStringContainsString('/pricing', $provider->request?->userPrompt ?? '');
        $this->assertSame('Two pages from the nav.', $plan['summary']);
        $this->assertSame('pricing', $plan['pages'][0]['slug']);
        $this->assertSame('pricing-2', $plan['pages'][1]['slug']);
        $this->assertSame(1.0, $plan['pages'][1]['confidence']);
    }

    public function test_generate_site_run_job_generates_pages_and_stores_zip(): void
    {
        Storage::fake('local');
        $project = $this->project();
        $source = $this->page($project, [
            'name' => 'Homepage',
            'html_source' => $this->markedHtml('Home'),
            'status' => 'valid',
        ]);
        $target = $this->page($project, [
            'name' => 'Pricing',
            'prompt' => 'Pricing brief',
            'status' => 'draft',
        ]);
        $run = $this->siteRun($project, $source, [
            ['target' => $target, 'name' => 'Pricing', 'slug' => 'pricing', 'brief' => 'Pricing brief'],
        ]);
        $this->app->instance(LlmProvider::class, new FakeSiteGenerationProvider($this->markedHtml('Pricing')));

        (new GenerateSiteRunJob($run->id, 'anthropic', 'claude-test', 'key'))->handle(
            app(Pipeline::class),
            app(RelatedPagePromptBuilder::class),
            app(SiteZipFinalizer::class),
            app(GenerationEventRecorder::class),
        );

        $run->refresh();
        $target->refresh();

        $this->assertSame('completed', $run->status);
        $this->assertSame('valid', $target->status);
        $this->assertNotNull($run->zip_path);
        Storage::disk('local')->assertExists($run->zip_path);

        $zip = new \ZipArchive;
        $this->assertTrue($zip->open(Storage::disk('local')->path($run->zip_path)));
        $this->assertNotFalse($zip->locateName('homepage.html'));
        $this->assertNotFalse($zip->locateName('pricing.html'));
        $this->assertStringContainsString('Home', $zip->getFromName('homepage.html'));
        $this->assertStringContainsString('Pricing', $zip->getFromName('pricing.html'));
        $zip->close();
    }

    public function test_download_requires_team_access_and_completed_run(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $user->createDefaultTeam();
        $this->actingAs($user);
        $project = Project::query()->create([
            'id' => app(IdGenerator::class)->project(),
            'name' => 'Acme',
        ]);
        $source = $this->page($project, [
            'name' => 'Homepage',
            'html_source' => $this->markedHtml('Home'),
            'status' => 'valid',
        ]);
        $run = SiteGenerationRun::query()->create([
            'id' => app(IdGenerator::class)->siteGenerationRun(),
            'team_id' => $project->team_id,
            'project_id' => $project->id,
            'source_page_id' => $source->id,
            'status' => 'completed',
            'planned_pages' => [],
            'generated_page_ids' => [],
            'zip_disk' => 'local',
            'zip_path' => 'site-runs/test/acme.zip',
            'zip_filename' => 'acme.zip',
        ]);
        Storage::disk('local')->put($run->zip_path, 'zip-bytes');

        $this->get(route('builder.pages.site-runs.download', [$project, $source, $run]))
            ->assertOk()
            ->assertDownload('acme.zip');

        $other = User::factory()->create();
        $other->createDefaultTeam();
        $this->actingAs($other);

        $this->get(route('builder.pages.site-runs.download', [$project, $source, $run]))
            ->assertNotFound();
    }

    private function project(): Project
    {
        return Project::query()->create([
            'id' => app(IdGenerator::class)->project(),
            'name' => 'Acme',
            'team_id' => $this->team()->id,
        ]);
    }

    private function team(): Team
    {
        $user = User::factory()->create();
        $team = $user->createDefaultTeam();
        $this->actingAs($user);
        app(TeamProviderCredentials::class)->save($team, 'anthropic', 'key');

        return $team;
    }

    private function page(Project $project, array $overrides = []): Page
    {
        return Page::query()->create($overrides + [
            'id' => app(IdGenerator::class)->page(),
            'project_id' => $project->id,
            'team_id' => $project->team_id,
            'name' => 'Page',
            'prompt' => '',
            'status' => 'draft',
        ]);
    }

    /**
     * @param  array<int, array{target: Page, name: string, slug: string, brief: string}>  $pages
     */
    private function siteRun(Project $project, Page $source, array $pages): SiteGenerationRun
    {
        $run = SiteGenerationRun::query()->create([
            'id' => app(IdGenerator::class)->siteGenerationRun(),
            'team_id' => $project->team_id,
            'project_id' => $project->id,
            'source_page_id' => $source->id,
            'status' => 'queued',
            'planned_pages' => [],
            'generated_page_ids' => [],
            'zip_disk' => 'local',
        ]);

        foreach ($pages as $index => $page) {
            SiteGenerationRunPage::query()->create([
                'id' => app(IdGenerator::class)->siteGenerationRunPage(),
                'site_generation_run_id' => $run->id,
                'target_page_id' => $page['target']->id,
                'sort_order' => $index,
                'name' => $page['name'],
                'slug' => $page['slug'],
                'brief' => $page['brief'],
                'source' => 'planner',
                'status' => 'queued',
            ]);
        }

        return $run;
    }

    private function markedHtml(string $title): string
    {
        return '<!-- tw:block id="block_hero" type="hero" label="Hero" --><section><h1>'.$title.'</h1></section><!-- /tw:block -->';
    }
}

class FakePlannerProvider implements LlmProvider
{
    public ?StructuredRequest $request = null;

    public function __construct(private readonly array $output) {}

    public function sendStructured(StructuredRequest $request): StructuredResponse
    {
        $this->request = $request;

        return new StructuredResponse($request->stage, $request->model, $this->output);
    }
}

class FakeSiteGenerationProvider implements LlmProvider
{
    public function __construct(private readonly string $html) {}

    public function sendStructured(StructuredRequest $request): StructuredResponse
    {
        throw new \RuntimeException("Unexpected structured request [{$request->stage}].");
    }

    public function sendTextStream(TextRequest $request, callable $onDelta): TextResponse
    {
        $onDelta($this->html, 0);

        return new TextResponse($request->stage, $request->model, $this->html);
    }
}
