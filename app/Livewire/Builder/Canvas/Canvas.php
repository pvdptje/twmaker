<?php

namespace App\Livewire\Builder\Canvas;

use App\Models\Page;
use App\Services\Rendering\Renderer;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Reactive;
use Livewire\Component;

class Canvas extends Component
{
    public Page $page;

    #[Reactive]
    public ?string $selectedNodeId = null;

    public function selectNode(?string $nodeId = null): void
    {
        $this->dispatch('node-selected', nodeId: $nodeId);
    }

    public function updatedSelectedNodeId(): void
    {
        $this->dispatch('preview-selection-changed', nodeId: $this->selectedNodeId);
    }

    public function render(): View
    {
        return view()->file(__DIR__.'/canvas.blade.php', [
            'srcdoc' => $this->previewSource(app(Renderer::class)),
        ]);
    }

    private function previewSource(Renderer $renderer): string
    {
        if (is_string($this->page->html_source) && trim($this->page->html_source) !== '') {
            return $renderer->renderPreviewHtml($this->page->html_source, $this->page->name);
        }

        return $renderer->renderPreviewHtml(
            '<main class="flex min-h-screen items-center justify-center bg-white px-6 text-neutral-500"><p>No generated HTML yet.</p></main>',
            $this->page->name,
        );
    }
}
