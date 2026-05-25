<?php

namespace App\Livewire\Projects\ProjectDashboard;

use App\Jobs\GenerateSiteRunJob;
use App\Models\Page;
use App\Models\Project;
use App\Models\SiteGenerationRun;
use App\Models\SiteGenerationRunPage;
use App\Services\Generation\SitePagePlanner;
use App\Services\Ids\IdGenerator;
use App\Services\Llm\LlmRegistry;
use App\Services\Llm\TeamProviderCredentials;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Component;
use Throwable;

class ProjectDashboard extends Component
{
    public Project $project;

    public string $name = '';

    public string $prompt = '';

    public ?string $editingPageId = null;

    public string $editingPageName = '';

    public string $editingPagePrompt = '';

    public bool $sitePlannerOpen = false;

    public ?string $siteSourcePageId = null;

    public string $siteProvider = '';

    public string $siteModel = '';

    public string $sitePlannerSummary = '';

    public ?string $sitePlanningError = null;

    /**
     * @var array<int, array{name: string, slug: string, brief: string, source: string, source_label: string, reason: string, confidence: float}>
     */
    public array $siteProposals = [];

    public function mount(Project $project): void
    {
        abort_unless($this->canAccessProject($project), 404);

        $this->project = $project;
        $this->siteProvider = $this->defaultSiteProvider();
        $this->siteModel = $this->defaultSiteModel($this->siteProvider);
    }

    public function createPage(IdGenerator $ids): mixed
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:160'],
            'prompt' => ['nullable', 'string'],
        ]);

        $page = Page::query()->create([
            'id' => $ids->page(),
            'project_id' => $this->project->id,
            'team_id' => $this->project->team_id,
            'name' => $validated['name'],
            'prompt' => $validated['prompt'] ?: '',
            'html_source' => null,
            'rendered_html_cache' => null,
            'status' => 'draft',
            'last_generation_summary' => null,
        ]);

        return $this->redirectRoute('builder.workspace', [$this->project, $page], navigate: true);
    }

    public function startRenamingPage(string $pageId): void
    {
        $page = $this->project->pages()->findOrFail($pageId);

        $this->editingPageId = $page->id;
        $this->editingPageName = $page->name;
        $this->editingPagePrompt = $page->prompt;
        $this->resetValidation(['editingPageName', 'editingPagePrompt']);
    }

    public function cancelRenamingPage(): void
    {
        $this->editingPageId = null;
        $this->editingPageName = '';
        $this->editingPagePrompt = '';
        $this->resetValidation(['editingPageName', 'editingPagePrompt']);
    }

    public function renamePage(): void
    {
        $validated = $this->validate([
            'editingPageName' => ['required', 'string', 'max:160'],
            'editingPagePrompt' => ['nullable', 'string'],
        ]);

        $page = $this->project->pages()->findOrFail($this->editingPageId);

        $page->update([
            'name' => $validated['editingPageName'],
            'prompt' => $validated['editingPagePrompt'] ?: '',
        ]);

        $this->cancelRenamingPage();
    }

    public function deletePage(string $pageId): void
    {
        $this->project->pages()->findOrFail($pageId)->delete();

        if ($this->editingPageId === $pageId) {
            $this->cancelRenamingPage();
        }
    }

    public function openGenerateSite(string $pageId): void
    {
        $page = $this->project->pages()->findOrFail($pageId);

        $this->sitePlannerOpen = true;
        $this->siteSourcePageId = $page->id;
        $this->siteProposals = [];
        $this->sitePlannerSummary = '';
        $this->sitePlanningError = null;
        $this->siteProvider = $this->normalizedSiteProvider($this->siteProvider);
        $this->siteModel = $this->normalizedSiteModel($this->siteProvider, $this->siteModel);

        if (trim((string) ($page->html_source ?? '')) === '') {
            $this->sitePlanningError = 'Generate this page before generating a site.';

            return;
        }

        $this->planSitePages();
    }

    public function closeGenerateSite(): void
    {
        $this->sitePlannerOpen = false;
        $this->siteSourcePageId = null;
        $this->siteProposals = [];
        $this->sitePlannerSummary = '';
        $this->sitePlanningError = null;
    }

    public function refreshSiteModel(): void
    {
        $this->siteProvider = $this->normalizedSiteProvider($this->siteProvider);
        $this->siteModel = $this->defaultSiteModel($this->siteProvider);
    }

    public function planSitePages(): void
    {
        if ($this->siteSourcePageId === null) {
            $this->sitePlanningError = 'Choose a source page first.';

            return;
        }

        $sourcePage = $this->project->pages()->findOrFail($this->siteSourcePageId);
        $provider = $this->normalizedSiteProvider($this->siteProvider);
        $model = $this->normalizedSiteModel($provider, $this->siteModel);

        $this->siteProvider = $provider;
        $this->siteModel = $model;
        $this->sitePlanningError = null;
        $this->siteProposals = [];
        $this->sitePlannerSummary = '';

        try {
            $plan = app(SitePagePlanner::class)->plan(
                $sourcePage,
                $this->project,
                $provider,
                $model !== '' ? $model : null,
                $this->apiKey($provider),
            );

            if (($plan['pages'] ?? []) === []) {
                $this->sitePlanningError = 'The planner did not return any pages.';

                return;
            }

            $this->sitePlannerSummary = $plan['summary'] ?? '';
            $this->siteProposals = $plan['pages'];
        } catch (Throwable $exception) {
            report($exception);

            $this->sitePlanningError = 'Could not plan the site: '.$exception->getMessage();
        }
    }

    public function removeSiteProposal(int $index): void
    {
        if (! array_key_exists($index, $this->siteProposals)) {
            return;
        }

        array_splice($this->siteProposals, $index, 1);

        if ($this->siteProposals === []) {
            $this->sitePlanningError = 'Add at least one page by recalculating before proceeding.';
        }
    }

    public function proceedGenerateSite(IdGenerator $ids): void
    {
        if ($this->siteSourcePageId === null) {
            $this->sitePlanningError = 'Choose a source page first.';

            return;
        }

        $sourcePage = $this->project->pages()->findOrFail($this->siteSourcePageId);
        $proposals = array_values(array_filter(
            $this->siteProposals,
            fn (mixed $proposal): bool => is_array($proposal) && trim((string) ($proposal['name'] ?? '')) !== '',
        ));

        if ($proposals === []) {
            $this->sitePlanningError = 'Keep at least one page before proceeding.';

            return;
        }

        $provider = $this->normalizedSiteProvider($this->siteProvider);
        $model = $this->normalizedSiteModel($provider, $this->siteModel);
        $apiKey = $this->apiKey($provider);

        $run = DB::transaction(function () use ($ids, $sourcePage, $proposals, $provider, $model): SiteGenerationRun {
            $run = SiteGenerationRun::query()->create([
                'id' => $ids->siteGenerationRun(),
                'team_id' => $this->project->team_id,
                'project_id' => $this->project->id,
                'source_page_id' => $sourcePage->id,
                'status' => 'queued',
                'provider' => $provider,
                'model' => $model !== '' ? $model : null,
                'planned_pages' => $proposals,
                'generated_page_ids' => [],
                'zip_disk' => 'local',
            ]);

            foreach ($proposals as $index => $proposal) {
                $name = trim((string) ($proposal['name'] ?? 'Generated page'));
                $brief = trim((string) ($proposal['brief'] ?? 'Create a focused page for '.$name.'.'));
                $slug = Str::slug((string) ($proposal['slug'] ?? $name)) ?: Str::slug($name);

                $targetPage = Page::query()->create([
                    'id' => app(IdGenerator::class)->page(),
                    'project_id' => $this->project->id,
                    'team_id' => $this->project->team_id,
                    'name' => $name,
                    'prompt' => $brief,
                    'html_source' => null,
                    'rendered_html_cache' => null,
                    'status' => 'draft',
                    'last_generation_summary' => null,
                ]);

                SiteGenerationRunPage::query()->create([
                    'id' => app(IdGenerator::class)->siteGenerationRunPage(),
                    'site_generation_run_id' => $run->id,
                    'target_page_id' => $targetPage->id,
                    'sort_order' => $index,
                    'name' => $name,
                    'slug' => $slug,
                    'brief' => $brief,
                    'source' => in_array(($proposal['source'] ?? null), ['menu', 'planner', 'user'], true) ? (string) $proposal['source'] : 'planner',
                    'status' => 'queued',
                ]);
            }

            return $run;
        });

        GenerateSiteRunJob::dispatch($run->id, $provider, $model !== '' ? $model : null, $apiKey);

        $this->closeGenerateSite();
    }

    public function render(): View
    {
        return view()->file(__DIR__.'/project-dashboard.blade.php', [
            'pages' => $this->project->pages()
                ->with(['siteGenerationRuns.pages'])
                ->latest()
                ->get(),
            'siteProviderOptions' => $this->siteProviderOptions(),
            'siteModelOptions' => $this->siteModelOptions($this->siteProvider),
        ])->layout('components.layouts.app', ['title' => $this->project->name]);
    }

    private function canAccessProject(Project $project): bool
    {
        $teamId = $project->team_id;

        return is_string($teamId)
            && $teamId !== ''
            && auth()->user()->teams()->whereKey($teamId)->exists();
    }

    /**
     * @return array<int, array{id: string, label: string, driver: string, models_refreshed_at: mixed}>
     */
    private function siteProviderOptions(): array
    {
        $team = $this->project->team;
        $configured = $this->credentials()->configuredProviderOptions($team);

        if ($configured !== []) {
            return $configured;
        }

        return collect($this->registry()->implementedProviders())
            ->filter(fn (array $provider): bool => $this->credentials()->canFetchModels($team, (string) $provider['id']))
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id: string, label: string, modalities: array<int, string>}>
     */
    private function siteModelOptions(string $provider): array
    {
        $provider = $this->normalizedSiteProvider($provider);
        $models = $this->registry()->modelOptions($provider, $this->apiKey($provider));

        if ($models !== []) {
            return $models;
        }

        $default = (string) config("llm.providers.{$provider}.models.section_generator", '');

        return $default !== '' ? [[
            'id' => $default,
            'label' => $default,
            'modalities' => ['text'],
        ]] : [];
    }

    private function defaultSiteProvider(): string
    {
        $providers = $this->siteProviderOptions();

        return (string) ($providers[0]['id'] ?? $this->registry()->defaultProvider());
    }

    private function defaultSiteModel(string $provider): string
    {
        $provider = $this->normalizedSiteProvider($provider);
        $model = $this->registry()->defaultModel($provider, 'section_generator', $this->apiKey($provider));

        if ($model !== '') {
            return $model;
        }

        return (string) config("llm.providers.{$provider}.models.section_generator", '');
    }

    private function normalizedSiteProvider(?string $provider): string
    {
        $provider = is_string($provider) ? trim($provider) : '';
        $ids = array_column($this->siteProviderOptions(), 'id');

        return in_array($provider, $ids, true)
            ? $provider
            : $this->defaultSiteProvider();
    }

    private function normalizedSiteModel(string $provider, ?string $model): string
    {
        $model = is_string($model) ? trim($model) : '';
        $ids = array_column($this->siteModelOptions($provider), 'id');

        if ($model !== '' && ($ids === [] || in_array($model, $ids, true))) {
            return $model;
        }

        return $this->defaultSiteModel($provider);
    }

    private function apiKey(string $provider): ?string
    {
        return $this->credentials()->apiKey($this->project->team, $provider);
    }

    private function credentials(): TeamProviderCredentials
    {
        return app(TeamProviderCredentials::class);
    }

    private function registry(): LlmRegistry
    {
        return app(LlmRegistry::class);
    }
}
