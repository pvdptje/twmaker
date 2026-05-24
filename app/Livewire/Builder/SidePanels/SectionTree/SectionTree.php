<?php

namespace App\Livewire\Builder\SidePanels\SectionTree;

use App\Jobs\InsertSectionJob;
use App\Models\Page;
use App\Services\Generation\GenerationEventRecorder;
use App\Services\Llm\LlmRegistry;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Reactive;
use Livewire\Component;

class SectionTree extends Component
{
    public Page $page;

    #[Reactive]
    public array $blockIndex = [];

    #[Reactive]
    public array $selectedBlockIds = [];

    public bool $insertOpen = false;

    public ?string $insertAnchorBlockId = null;

    public string $insertPosition = 'after';

    public string $insertInstruction = '';

    public string $insertProvider = '';

    public string $insertModel = '';

    public string $insertApiKey = '';

    public function openInsert(?string $anchorBlockId = null, string $position = 'after'): void
    {
        $this->insertOpen = true;
        $this->insertAnchorBlockId = $anchorBlockId !== '' ? $anchorBlockId : null;
        $this->insertPosition = $position === 'before' ? 'before' : 'after';
        $this->insertInstruction = '';
        $this->resetErrorBag('insertInstruction');
    }

    public function cancelInsert(): void
    {
        $this->insertOpen = false;
        $this->insertAnchorBlockId = null;
        $this->insertPosition = 'after';
        $this->insertInstruction = '';
        $this->resetErrorBag();
    }

    public function insertSectionWithSelection(?string $provider = null, ?string $model = null, ?string $apiKey = null): void
    {
        $this->insertProvider = $this->normalizedProvider($provider);
        $this->insertApiKey = (string) $apiKey;
        $this->insertModel = $this->normalizedModel($this->insertProvider, $model);

        $this->insertSection();
    }

    public function insertSection(): void
    {
        $this->validate([
            'insertAnchorBlockId' => ['nullable', 'string'],
            'insertPosition' => ['required', 'string', 'in:before,after'],
            'insertInstruction' => ['required', 'string', 'min:3', 'max:5000'],
            'insertProvider' => ['nullable', 'string'],
            'insertModel' => ['nullable', 'string'],
            'insertApiKey' => ['nullable', 'string', 'max:500'],
        ]);

        $anchorId = $this->insertAnchorBlockId !== '' ? $this->insertAnchorBlockId : null;
        $position = $this->insertPosition === 'before' ? 'before' : 'after';

        app(GenerationEventRecorder::class)->record(
            $this->page,
            'insert_requested',
            'section_inserter',
            'info',
            $this->insertRequestedSummary($anchorId, $position),
            $anchorId,
            [
                'instruction' => $this->insertInstruction,
                'anchor_id' => $anchorId,
                'position' => $position,
            ],
        );

        $this->page->forceFill(['status' => 'generating'])->save();
        $this->dispatch('generation-started', pageId: $this->page->id, stage: 'section_inserter');

        InsertSectionJob::dispatch(
            $this->page->id,
            $anchorId,
            $position,
            $this->insertInstruction,
            $this->insertProvider !== '' ? $this->insertProvider : null,
            $this->insertModel !== '' ? $this->insertModel : null,
            $this->normalizedApiKey(),
        );

        $this->cancelInsert();
    }

    public function render(): View
    {
        return view()->file(__DIR__.'/section-tree.blade.php');
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

        return $this->registry()->defaultModel($provider, 'targeted_edit', $this->normalizedApiKey());
    }

    private function normalizedApiKey(): ?string
    {
        $apiKey = trim($this->insertApiKey);

        return $apiKey === '' ? null : $apiKey;
    }

    private function registry(): LlmRegistry
    {
        return app(LlmRegistry::class);
    }

    private function insertRequestedSummary(?string $anchorBlockId, string $position): string
    {
        if ($anchorBlockId === null || $anchorBlockId === '') {
            return $position === 'before' ? 'Inserting section at top of page.' : 'Inserting section at end of page.';
        }

        return 'Inserting section '.$position.' selected block.';
    }
}
