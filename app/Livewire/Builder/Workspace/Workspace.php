<?php

namespace App\Livewire\Builder\Workspace;

use App\Models\Page;
use App\Models\Project;
use App\Services\Html\BlockIndexer;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class Workspace extends Component
{
    public string $page_id;

    public ?string $selected_node_id = null;

    public array $selected_block_ids = [];

    public array $block_index = [];

    public string $generation_status = 'idle';

    public string $preview_signature = '';

    public Project $project;

    public Page $page;

    public function mount(Project $project, Page $page): void
    {
        abort_unless($page->project_id === $project->id, 404);

        $this->project = $project;
        $this->page = $page;
        $this->page_id = $page->id;
        $this->block_index = $this->slimBlockIndex($this->currentBlockIndex($page));
        $this->preview_signature = $this->pageSignature($page);
    }

    #[On('node-selected')]
    public function selectNode(?string $nodeId = null, bool $scrollIntoView = true): void
    {
        $this->selected_node_id = $nodeId;
        $this->dispatch('preview-selection-changed', nodeId: $nodeId, scrollIntoView: $scrollIntoView);
    }

    #[On('block-selection-toggled')]
    public function toggleBlockSelection(string $blockId): void
    {
        if ($blockId === '') {
            return;
        }

        if (in_array($blockId, $this->selected_block_ids, true)) {
            $this->selected_block_ids = array_values(array_diff($this->selected_block_ids, [$blockId]));

            return;
        }

        $this->selected_block_ids[] = $blockId;
    }

    #[On('generation-started')]
    public function generationStarted(string $pageId): void
    {
        if ($pageId === $this->page_id) {
            $this->generation_status = 'running';
        }
    }

    #[On('generation-finished')]
    public function generationFinished(string $pageId, string $status): void
    {
        if ($pageId !== $this->page_id) {
            return;
        }

        $this->page->refresh();
        $this->block_index = $this->slimBlockIndex($this->currentBlockIndex($this->page));
        $this->preview_signature = $this->pageSignature($this->page);
        $this->generation_status = match ($status) {
            'generating' => 'running',
            'valid' => 'valid',
            'error' => 'error',
            default => 'idle',
        };
    }

    public function refreshFromPage(): void
    {
        $this->page->refresh();

        $signature = $this->pageSignature($this->page);
        if ($signature !== $this->preview_signature) {
            $this->block_index = $this->slimBlockIndex($this->currentBlockIndex($this->page));
            $this->selected_block_ids = $this->validSelectedBlockIds($this->selected_block_ids);
            $this->preview_signature = $signature;

            if ($this->selected_node_id !== null) {
                $this->dispatch('preview-selection-changed', nodeId: $this->selected_node_id, scrollIntoView: true);
            }
        }

        $this->generation_status = match ($this->page->status) {
            'generating' => 'running',
            'valid' => 'valid',
            'error' => 'error',
            default => $this->generation_status,
        };
    }

    public function render(): View
    {
        return view()->file(__DIR__.'/workspace.blade.php')->layout('components.layouts.app', [
            'title' => $this->page->name,
        ]);
    }

    private function pageSignature(Page $page): string
    {
        return md5(implode('|', [
            $page->status,
            (string) $page->updated_at,
            md5((string) ($page->html_source ?? '')),
            md5(json_encode($page->block_index ?? [], JSON_THROW_ON_ERROR)),
        ]));
    }

    private function slimBlockIndex(array $blocks): array
    {
        return array_map(
            fn (array $block): array => [
                'id' => (string) ($block['id'] ?? ''),
                'type' => (string) ($block['type'] ?? 'block'),
                'label' => (string) ($block['label'] ?? $block['type'] ?? 'Block'),
                'summary' => (string) ($block['summary'] ?? ''),
            ],
            $blocks,
        );
    }

    private function currentBlockIndex(Page $page): array
    {
        $html = (string) ($page->html_source ?? '');
        $blocks = trim($html) === '' ? [] : app(BlockIndexer::class)->index($html);

        return $blocks !== [] ? $blocks : ($page->block_index ?? []);
    }

    private function validSelectedBlockIds(array $selectedBlockIds): array
    {
        $known = array_flip(array_column($this->block_index, 'id'));

        return array_values(array_filter(
            $selectedBlockIds,
            fn (mixed $id): bool => is_string($id) && isset($known[$id]),
        ));
    }
}
