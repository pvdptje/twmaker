<?php

namespace App\Livewire\Builder\LeftSidebar;

use App\Jobs\GenerateRelatedPageJob;
use App\Models\Page;
use App\Models\Project;
use App\Services\Generation\GenerationEventRecorder;
use App\Services\Ids\IdGenerator;
use App\Services\Llm\LlmRegistry;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Reactive;
use Livewire\Component;

class LeftSidebar extends Component
{
    public Project $project;

    #[Reactive]
    public Page $page;

    #[Reactive]
    public array $blockIndex = [];

    #[Reactive]
    public ?string $selectedNodeId = null;

    #[Reactive]
    public array $selectedBlockIds = [];

    public bool $createRelatedOpen = false;

    public string $relatedPageName = '';

    public string $relatedPageBrief = '';

    public string $relatedProvider = '';

    public string $relatedModel = '';

    public string $relatedApiKey = '';

    public function openCreateRelatedPage(): void
    {
        $this->createRelatedOpen = true;
        $this->relatedPageName = '';
        $this->relatedPageBrief = '';
        $this->resetErrorBag();
    }

    public function cancelCreateRelatedPage(): void
    {
        $this->createRelatedOpen = false;
        $this->relatedPageName = '';
        $this->relatedPageBrief = '';
        $this->resetErrorBag();
    }

    public function createRelatedPageWithSelection(?string $provider = null, ?string $model = null, ?string $apiKey = null): mixed
    {
        $this->relatedProvider = $this->normalizedProvider($provider);
        $this->relatedApiKey = (string) $apiKey;
        $this->relatedModel = $this->normalizedModel($this->relatedProvider, $model);

        return $this->createRelatedPage();
    }

    public function createRelatedPage(): mixed
    {
        $validated = $this->validate([
            'relatedPageName' => ['required', 'string', 'max:160'],
            'relatedPageBrief' => ['nullable', 'string', 'max:2000'],
            'relatedProvider' => ['nullable', 'string'],
            'relatedModel' => ['nullable', 'string'],
            'relatedApiKey' => ['nullable', 'string', 'max:500'],
        ]);

        if (trim((string) ($this->page->html_source ?? '')) === '') {
            $this->addError('relatedPageName', 'Generate the current page before creating a related page.');

            return null;
        }

        $brief = trim((string) ($validated['relatedPageBrief'] ?? ''));
        $prompt = trim($brief) !== ''
            ? $brief
            : 'Created from '.$this->page->name.' with the same design style.';

        $newPage = Page::query()->create([
            'id' => app(IdGenerator::class)->page(),
            'project_id' => $this->project->id,
            'team_id' => $this->project->team_id,
            'name' => trim((string) $validated['relatedPageName']),
            'prompt' => $prompt,
            'html_source' => null,
            'rendered_html_cache' => null,
            'status' => 'generating',
            'last_generation_summary' => null,
        ]);

        app(GenerationEventRecorder::class)->record(
            $newPage,
            'related_page_requested',
            'section_generator',
            'info',
            'Creating a new page from '.$this->page->name.'.',
            payload: [
                'source_page_id' => $this->page->id,
                'source_page_name' => $this->page->name,
                'brief' => $brief,
            ],
        );

        GenerateRelatedPageJob::dispatch(
            $this->page->id,
            $newPage->id,
            $brief,
            $this->relatedProvider !== '' ? $this->relatedProvider : null,
            $this->relatedModel !== '' ? $this->relatedModel : null,
            $this->normalizedApiKey(),
        );

        return $this->redirectRoute('builder.workspace', [$this->project, $newPage], navigate: true);
    }

    public function render(): View
    {
        return view()->file(__DIR__.'/left-sidebar.blade.php');
    }

    private function normalizedProvider(?string $provider): string
    {
        $provider = is_string($provider) ? trim($provider) : '';

        return $this->registry()->isImplementedProvider($provider)
            ? $provider
            : $this->registry()->defaultProvider();
    }

    private function normalizedModel(string $provider, ?string $model): string
    {
        $model = is_string($model) ? trim($model) : '';
        $modelIds = $this->registry()->modelIds($provider, $this->normalizedApiKey());

        if ($model !== '' && in_array($model, $modelIds, true)) {
            return $model;
        }

        return $this->registry()->defaultModel($provider, 'section_generator', $this->normalizedApiKey());
    }

    private function normalizedApiKey(): ?string
    {
        $apiKey = trim($this->relatedApiKey);

        return $apiKey === '' ? null : $apiKey;
    }

    private function registry(): LlmRegistry
    {
        return app(LlmRegistry::class);
    }
}
