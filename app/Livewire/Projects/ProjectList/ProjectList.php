<?php

namespace App\Livewire\Projects\ProjectList;

use App\Models\Project;
use App\Services\Ids\IdGenerator;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class ProjectList extends Component
{
    public string $name = '';

    public string $description = '';

    public function createProject(IdGenerator $ids): mixed
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string'],
        ]);

        $project = Project::query()->create([
            'id' => $ids->project(),
            'name' => $validated['name'],
            'description' => $validated['description'] ?: null,
            'default_design_preferences' => null,
        ]);

        return $this->redirectRoute('projects.show', $project, navigate: true);
    }

    public function render(): View
    {
        return view()->file(__DIR__.'/project-list.blade.php', [
            'projects' => Project::query()->latest()->get(),
        ])->layout('components.layouts.app', ['title' => 'Projects']);
    }
}
