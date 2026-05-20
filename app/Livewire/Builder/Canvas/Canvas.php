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
        $this->srcdoc = $renderer->renderPreviewDocument($this->document, $this->library());
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

    private function library(): array
    {
        return $this->page->project
            ->reusableElements()
            ->get()
            ->keyBy('id')
            ->map(fn ($element): array => [
                'id' => $element->id,
                'type' => $element->type,
                'default_props' => $element->default_props,
            ])
            ->all();
    }
}
