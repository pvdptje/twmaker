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

    public array $document = [];

    #[Reactive]
    public ?string $selectedNodeId = null;

    public string $srcdoc = '';

    public function mount(Renderer $renderer): void
    {
        if (is_string($this->page->html_source) && trim($this->page->html_source) !== '') {
            $this->srcdoc = $renderer->renderPreviewHtml($this->page->html_source, $this->page->name);

            return;
        }

        if (($this->document['schema_version'] ?? null) === 2) {
            $this->srcdoc = $renderer->renderPreviewHtml(
                '<main class="flex min-h-screen items-center justify-center bg-white px-6 text-neutral-500"><p>No generated HTML yet.</p></main>',
                $this->page->name,
            );

            return;
        }

        $this->srcdoc = $renderer->renderPreviewDocument($this->document);
    }

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
        return view()->file(__DIR__.'/canvas.blade.php');
    }
}
