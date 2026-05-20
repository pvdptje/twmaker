<?php

namespace App\Livewire\Builder\Workspace;

use App\Models\Page;
use App\Models\Project;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class Workspace extends Component
{
    public string $page_id;

    public ?string $selected_node_id = null;

    public array $document = [];

    public array $library = [];

    public string $generation_status = 'idle';

    public Project $project;

    public Page $page;

    public function mount(Project $project, Page $page): void
    {
        abort_unless($page->project_id === $project->id, 404);

        $this->project = $project;
        $this->page = $page;
        $this->page_id = $page->id;
        $this->document = $page->document_json ?? [];
        $this->library = $project->reusableElements()
            ->get()
            ->keyBy('id')
            ->map(fn ($element): array => [
                'id' => $element->id,
                'name' => $element->name,
                'type' => $element->type,
                'default_props' => $element->default_props,
            ])
            ->all();
    }

    #[On('node-selected')]
    public function selectNode(?string $nodeId = null): void
    {
        $this->selected_node_id = $nodeId;
    }

    public function render(): View
    {
        return view()->file(__DIR__.'/workspace.blade.php')->layout('components.layouts.app', [
            'title' => $this->page->name,
        ]);
    }
}
