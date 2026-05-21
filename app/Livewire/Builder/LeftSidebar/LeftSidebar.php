<?php

namespace App\Livewire\Builder\LeftSidebar;

use App\Models\Page;
use App\Models\Project;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Reactive;
use Livewire\Component;

class LeftSidebar extends Component
{
    public Project $project;

    #[Reactive]
    public Page $page;

    #[Reactive]
    public array $blockIndex = [];

    #[Reactive]
    public array $selectedBlockIds = [];

    public function render(): View
    {
        return view()->file(__DIR__.'/left-sidebar.blade.php');
    }
}
