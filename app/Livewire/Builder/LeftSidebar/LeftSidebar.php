<?php

namespace App\Livewire\Builder\LeftSidebar;

use App\Models\Page;
use App\Models\Project;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class LeftSidebar extends Component
{
    public Project $project;

    public Page $page;

    public array $document = [];

    public array $library = [];

    public function render(): View
    {
        return view()->file(__DIR__.'/left-sidebar.blade.php');
    }
}
