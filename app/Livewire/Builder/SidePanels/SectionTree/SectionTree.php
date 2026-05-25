<?php

namespace App\Livewire\Builder\SidePanels\SectionTree;

use App\Jobs\InsertSectionJob;
use App\Models\Page;
use App\Services\Generation\GenerationEventRecorder;
use App\Services\Generation\Pipeline;
use App\Services\Html\BlockIndexer;
use App\Services\Html\HtmlValidationException;
use App\Services\Llm\ImageAttachments;
use App\Services\Llm\LlmRegistry;
use App\Services\Llm\TeamProviderCredentials;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Reactive;
use Livewire\Component;

class SectionTree extends Component
{
    public Page $page;

    #[Reactive]
    public array $blockIndex = [];

    #[Reactive]
    public ?string $selectedNodeId = null;

    #[Reactive]
    public array $selectedBlockIds = [];

    public bool $insertOpen = false;

    public ?string $insertAnchorBlockId = null;

    public string $insertPosition = 'after';

    public string $insertInstruction = '';

    public string $insertProvider = '';

    public string $insertModel = '';

    public string $insertApiKey = '';

    /**
     * @var array<int, array{base64: string, mime_type: string}>
     */
    public array $insertImages = [];

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
        $this->insertImages = [];
        $this->resetErrorBag();
    }

    /**
     * @param  array<int, array{base64?: string, mime_type?: string}>|null  $attachments
     */
    public function insertSectionWithSelection(?string $provider = null, ?string $model = null, ?string $apiKey = null, ?array $attachments = null): void
    {
        $this->insertProvider = $this->normalizedProvider($provider);
        $this->insertApiKey = '';
        $this->insertModel = $this->normalizedModel($this->insertProvider, $model);
        $this->insertImages = $this->normalizedAttachments($attachments);

        if ($this->insertImages !== [] && ! $this->registry()->supportsModality($this->insertProvider, $this->insertModel, 'image', $this->normalizedApiKey())) {
            $this->addError('insertInstruction', 'The selected model does not accept image input. Pick a vision-capable model or remove the attachments.');

            return;
        }

        $this->insertSection();
    }

    public function insertSection(): void
    {
        $this->validate([
            'insertAnchorBlockId' => ['nullable', 'string'],
            'insertPosition' => ['required', 'string', 'in:before,after'],
            'insertInstruction' => ['required', 'string', 'min:3', 'max:5000'],
            'insertProvider' => ['nullable', 'string', 'in:'.implode(',', $this->providerIds())],
            'insertModel' => ['nullable', 'string'],
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
                'reference_images' => count($this->insertImages),
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
            $this->insertImages,
        );

        $this->cancelInsert();
    }

    public function moveBlock(string $sourceBlockId, string $targetBlockId, string $position): void
    {
        $sourceBlockId = trim($sourceBlockId);
        $targetBlockId = trim($targetBlockId);
        $position = $position === 'before' ? 'before' : 'after';

        if ($sourceBlockId === '' || $targetBlockId === '' || $sourceBlockId === $targetBlockId) {
            return;
        }

        $source = $this->outlineItem($sourceBlockId);
        $target = $this->outlineItem($targetBlockId);

        if ($source === null || $target === null) {
            return;
        }

        if (($source['parent_id'] ?? null) !== ($target['parent_id'] ?? null)) {
            $this->dispatch('section-move-failed', sourceBlockId: $sourceBlockId, errors: ['Grouped items can only be moved within their parent group.']);

            return;
        }

        $ids = array_values(array_map(
            fn (array $item): string => (string) ($item['id'] ?? ''),
            array_filter(
                $this->blockIndex,
                fn (array $item): bool => ($item['parent_id'] ?? null) === ($source['parent_id'] ?? null),
            ),
        ));
        $sourceIndex = array_search($sourceBlockId, $ids, true);
        $targetIndex = array_search($targetBlockId, $ids, true);

        if ($sourceIndex === false || $targetIndex === false) {
            return;
        }

        $insertionIndex = $position === 'before' ? $targetIndex : $targetIndex + 1;
        $normalizedDestination = $sourceIndex < $insertionIndex ? $insertionIndex - 1 : $insertionIndex;
        if ($sourceIndex === $normalizedDestination) {
            $this->dispatch('section-moved', sourceBlockId: $sourceBlockId, targetBlockId: $targetBlockId, position: $position);

            return;
        }

        try {
            app(Pipeline::class)->moveSection($this->page, $sourceBlockId, $targetBlockId, $position);
        } catch (HtmlValidationException $exception) {
            $this->dispatch('section-move-failed', sourceBlockId: $sourceBlockId, errors: $exception->errors);

            return;
        }

        $this->page->refresh();
        $this->dispatch('section-moved', sourceBlockId: $sourceBlockId, targetBlockId: $targetBlockId, position: $position);
        $this->dispatch('generation-finished', pageId: $this->page->id, status: $this->page->status);
    }

    public function copyBlockHtml(string $blockId): ?string
    {
        $blockId = trim($blockId);
        if ($blockId === '') {
            return null;
        }

        $source = (string) ($this->page->html_source ?? '');
        if ($source === '') {
            return null;
        }

        foreach (app(BlockIndexer::class)->indexSelectable($source) as $block) {
            if (($block['id'] ?? '') === $blockId) {
                return (string) ($block['html'] ?? '');
            }
        }

        return null;
    }

    public function removeBlock(string $blockId): void
    {
        $blockId = trim($blockId);
        if ($blockId === '') {
            return;
        }

        try {
            app(Pipeline::class)->removeSection($this->page, $blockId);
        } catch (HtmlValidationException $exception) {
            $this->dispatch('section-remove-failed', blockId: $blockId, errors: $exception->errors);

            return;
        }

        $this->page->refresh();
        $this->dispatch('section-removed', blockId: $blockId);
        $this->dispatch('generation-finished', pageId: $this->page->id, status: $this->page->status);
    }

    public function render(): View
    {
        return view()->file(__DIR__.'/section-tree.blade.php');
    }

    private function normalizedProvider(?string $provider): string
    {
        $provider = is_string($provider) ? trim($provider) : '';

        return in_array($provider, $this->providerIds(), true)
            ? $provider
            : $this->defaultProvider();
    }

    private function normalizedModel(string $provider, ?string $model): string
    {
        $model = is_string($model) ? trim($model) : '';
        $modelIds = $this->registry()->modelIds($provider, $this->apiKeyForProvider($provider));

        if ($model !== '' && in_array($model, $modelIds, true)) {
            return $model;
        }

        return $this->registry()->defaultModel($provider, 'targeted_edit', $this->apiKeyForProvider($provider));
    }

    /**
     * @param  array<int, array{base64?: string, mime_type?: string}>|null  $attachments
     * @return array<int, array{base64: string, mime_type: string}>
     */
    private function normalizedAttachments(?array $attachments): array
    {
        return app(ImageAttachments::class)->normalize($attachments);
    }

    private function normalizedApiKey(): ?string
    {
        return $this->apiKeyForProvider($this->insertProvider);
    }

    private function registry(): LlmRegistry
    {
        return app(LlmRegistry::class);
    }

    private function providerIds(): array
    {
        return array_column($this->credentials()->configuredProviderOptions($this->credentials()->teamForPage($this->page)), 'id');
    }

    private function defaultProvider(): string
    {
        return (string) ($this->providerIds()[0] ?? $this->registry()->defaultProvider());
    }

    private function apiKeyForProvider(string $provider): ?string
    {
        return $this->credentials()->apiKey($this->credentials()->teamForPage($this->page), $provider);
    }

    private function credentials(): TeamProviderCredentials
    {
        return app(TeamProviderCredentials::class);
    }

    private function insertRequestedSummary(?string $anchorBlockId, string $position): string
    {
        if ($anchorBlockId === null || $anchorBlockId === '') {
            return $position === 'before' ? 'Inserting section at top of page.' : 'Inserting section at end of page.';
        }

        return 'Inserting section '.$position.' selected block.';
    }

    private function outlineItem(string $id): ?array
    {
        foreach ($this->blockIndex as $item) {
            if (($item['id'] ?? null) === $id) {
                return $item;
            }
        }

        return null;
    }
}
