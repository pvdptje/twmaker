<?php

namespace App\Livewire\Projects\ProjectList;

use App\Models\Team;
use App\Services\Ids\IdGenerator;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class ProjectList extends Component
{
    public string $name = '';

    public string $description = '';

    public ?string $editingProjectId = null;

    public string $editingProjectName = '';

    public string $editingProjectDescription = '';

    public function createProject(IdGenerator $ids): mixed
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string'],
        ]);

        $project = $this->currentTeam()->projects()->create([
            'id' => $ids->project(),
            'name' => $validated['name'],
            'description' => $validated['description'] ?: null,
            'default_design_preferences' => null,
        ]);

        return $this->redirectRoute('projects.show', $project, navigate: true);
    }

    public function startRenamingProject(string $projectId): void
    {
        $project = $this->currentTeam()->projects()->findOrFail($projectId);

        $this->editingProjectId = $project->id;
        $this->editingProjectName = $project->name;
        $this->editingProjectDescription = (string) ($project->description ?? '');
        $this->resetValidation(['editingProjectName', 'editingProjectDescription']);
    }

    public function cancelRenamingProject(): void
    {
        $this->editingProjectId = null;
        $this->editingProjectName = '';
        $this->editingProjectDescription = '';
        $this->resetValidation(['editingProjectName', 'editingProjectDescription']);
    }

    public function renameProject(): void
    {
        $validated = $this->validate([
            'editingProjectName' => ['required', 'string', 'max:120'],
            'editingProjectDescription' => ['nullable', 'string'],
        ]);

        $project = $this->currentTeam()->projects()->findOrFail($this->editingProjectId);

        $project->update([
            'name' => $validated['editingProjectName'],
            'description' => $validated['editingProjectDescription'] ?: null,
        ]);

        $this->cancelRenamingProject();
    }

    public function deleteProject(string $projectId): void
    {
        $this->currentTeam()->projects()->findOrFail($projectId)->delete();

        if ($this->editingProjectId === $projectId) {
            $this->cancelRenamingProject();
        }
    }

    public function render(): View
    {
        return view()->file(__DIR__.'/project-list.blade.php', [
            'projects' => $this->currentTeam()->projects()->withCount('pages')->latest()->get(),
        ])->layout('components.layouts.app', ['title' => 'Projects']);
    }

    private function currentTeam(): Team
    {
        $user = auth()->user();
        abort_unless($user !== null, 403);

        return $user->createDefaultTeam();
    }
}
