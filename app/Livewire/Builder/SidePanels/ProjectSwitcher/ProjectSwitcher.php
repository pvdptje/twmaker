<?php

namespace App\Livewire\Builder\SidePanels\ProjectSwitcher;

use App\Models\Project;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class ProjectSwitcher extends Component
{
    public Project $project;

    public function render(): View
    {
        return view()->file(__DIR__.'/project-switcher.blade.php', [
            'projects' => Project::query()->orderBy('name')->get(),
        ]);
    }
}
