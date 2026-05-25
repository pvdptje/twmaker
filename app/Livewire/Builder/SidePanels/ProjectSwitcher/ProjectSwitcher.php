<?php

namespace App\Livewire\Builder\SidePanels\ProjectSwitcher;

use App\Models\Page;
use App\Models\Project;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class ProjectSwitcher extends Component
{
    public Project $project;

    public Page $page;

    public function render(): View
    {
        return view()->file(__DIR__.'/project-switcher.blade.php', [
            'pages' => $this->project->pages()->orderBy('name')->get(),
        ]);
    }
}
