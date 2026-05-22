<?php

namespace App\Livewire\Projects\ProjectDashboard;

use App\Models\Page;
use App\Models\Project;
use App\Services\Ids\IdGenerator;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class ProjectDashboard extends Component
{
    public Project $project;

    public string $name = '';

    public string $prompt = '';

    public function mount(Project $project): void
    {
        $this->project = $project;
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
            'name' => $validated['name'],
            'prompt' => $validated['prompt'] ?: '',
            'html_source' => null,
            'rendered_html_cache' => null,
            'status' => 'draft',
            'last_generation_summary' => null,
        ]);

        return $this->redirectRoute('builder.workspace', [$this->project, $page], navigate: true);
    }

    public function render(): View
    {
        return view()->file(__DIR__.'/project-dashboard.blade.php', [
            'pages' => $this->project->pages()->latest()->get(),
        ])->layout('components.layouts.app', ['title' => $this->project->name]);
    }
}
