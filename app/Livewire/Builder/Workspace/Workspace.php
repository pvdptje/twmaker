<?php

namespace App\Livewire\Builder\Workspace;

use App\Models\Page;
use App\Models\Project;
use App\Services\Html\BlockIndexer;
use App\Services\Html\HtmlValidationException;
use App\Services\Html\QuickElementEditor;
use App\Services\Rendering\Renderer;
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

    public string $preview_mount_key = '';

    public bool $deferred_workspace_sync = false;

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
        $this->preview_mount_key = $this->preview_signature;
    }

    #[On('node-selected')]
    public function selectNode(?string $nodeId = null, bool $scrollIntoView = true): void
    {
        $this->selected_node_id = $nodeId;

        if ($scrollIntoView) {
            $this->dispatch('preview-selection-changed', nodeId: $nodeId, scrollIntoView: true);
        }
    }

    #[On('quick-edit-save')]
    public function saveQuickEdit(string $editId, string $html, QuickElementEditor $editor): void
    {
        try {
            $updatedHtml = $editor->replace((string) ($this->page->html_source ?? ''), $editId, $html);
        } catch (HtmlValidationException $exception) {
            $this->dispatch('quick-edit-failed', errors: $exception->errors);

            return;
        }

        $this->page->forceFill([
            'html_source' => $updatedHtml,
            'status' => 'valid',
            'rendered_html_cache' => null,
        ])->save();

        $this->page->refresh();
        $this->block_index = $this->slimBlockIndex($this->currentBlockIndex($this->page));
        $this->selected_block_ids = $this->validSelectedBlockIds($this->selected_block_ids);
        $this->preview_signature = $this->pageSignature($this->page);

        $this->dispatch('quick-edit-saved', editId: $editId, html: $html);
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
    public function generationFinished(string $pageId, string $status, bool $incremental = false): void
    {
        if ($pageId !== $this->page_id) {
            return;
        }

        $this->page->refresh();
        $this->block_index = $this->slimBlockIndex($this->currentBlockIndex($this->page));
        $this->preview_signature = $this->pageSignature($this->page);

        if (! $incremental) {
            $this->preview_mount_key = $this->preview_signature;
            $this->dispatchPreviewHtmlUpdated();
        }

        $this->generation_status = match ($status) {
            'generating' => 'running',
            'valid' => 'valid',
            'error' => 'error',
            default => 'idle',
        };
    }

    public function refreshFromPage(): ?array
    {
        $this->page->refresh();

        if ($this->deferred_workspace_sync) {
            $this->block_index = $this->slimBlockIndex($this->currentBlockIndex($this->page));
            $this->selected_block_ids = $this->validSelectedBlockIds($this->selected_block_ids);
            $this->deferred_workspace_sync = false;
        }

        $signature = $this->pageSignature($this->page);
        if ($signature !== $this->preview_signature) {
            if ($patch = $this->latestTargetedEditPatch()) {
                $this->block_index = $this->slimBlockIndex($this->currentBlockIndex($this->page));
                $this->selected_block_ids = $this->validSelectedBlockIds($this->selected_block_ids);
                $this->preview_signature = $signature;
                $this->generation_status = 'valid';

                $this->dispatchTargetedEditApplied($patch);

                return $patch;
            }

            if ($this->generation_status === 'running' && $this->page->status === 'valid') {
                return null;
            }

            $this->block_index = $this->slimBlockIndex($this->currentBlockIndex($this->page));
            $this->selected_block_ids = $this->validSelectedBlockIds($this->selected_block_ids);
            $this->preview_signature = $signature;
            $this->preview_mount_key = $signature;
            $this->dispatchPreviewHtmlUpdated();

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

        return null;
    }

    public function refreshPreviewFromPage(): ?array
    {
        $this->page->refresh();

        $signature = $this->pageSignature($this->page);
        if ($signature === $this->preview_signature) {
            return null;
        }

        $patch = $this->latestTargetedEditPatch();
        if (! $patch) {
            return null;
        }

        $this->preview_signature = $signature;
        $this->generation_status = 'valid';
        $this->deferred_workspace_sync = true;
        $this->dispatchTargetedEditApplied($patch);

        return $patch;
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

        return trim($html) === '' ? [] : app(BlockIndexer::class)->index($html);
    }

    private function validSelectedBlockIds(array $selectedBlockIds): array
    {
        $known = array_flip(array_column($this->block_index, 'id'));

        return array_values(array_filter(
            $selectedBlockIds,
            fn (mixed $id): bool => is_string($id) && isset($known[$id]),
        ));
    }

    private function dispatchPreviewHtmlUpdated(): void
    {
        $this->dispatch(
            'preview-html-updated',
            srcdoc: $this->previewSource(),
            selectedNodeId: $this->selected_node_id,
        );
    }

    private function latestTargetedEditPatch(): ?array
    {
        $event = $this->page->generationEvents()
            ->where('kind', 'edit_applied')
            ->where('stage', 'targeted_edit')
            ->latest('occurred_at')
            ->first();

        $payload = is_array($event?->payload) ? $event->payload : [];
        $targetIds = $payload['target_ids'] ?? null;
        $htmlSource = $payload['html_source'] ?? null;

        if (! is_array($targetIds) || $targetIds === [] || ! is_string($htmlSource) || $htmlSource === '') {
            return null;
        }

        if (! str_contains((string) ($this->page->html_source ?? ''), $htmlSource)) {
            return null;
        }

        return [
            'targetIds' => array_values($targetIds),
            'html' => $htmlSource,
        ];
    }

    private function dispatchTargetedEditApplied(array $patch): void
    {
        $this->dispatch('targeted-edit-applied', targetIds: $patch['targetIds'], html: $patch['html']);
    }

    private function previewSource(): string
    {
        $renderer = app(Renderer::class);

        if (is_string($this->page->html_source) && trim($this->page->html_source) !== '') {
            return $renderer->renderPreviewHtml($this->page->html_source, $this->page->name);
        }

        return $renderer->renderPreviewHtml(
            '<main class="flex min-h-screen items-center justify-center bg-white px-6 text-neutral-500"><p>No generated HTML yet.</p></main>',
            $this->page->name,
        );
    }
}
