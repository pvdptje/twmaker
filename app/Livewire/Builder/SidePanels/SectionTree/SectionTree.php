<?php

namespace App\Livewire\Builder\SidePanels\SectionTree;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Reactive;
use Livewire\Component;

class SectionTree extends Component
{
    #[Reactive]
    public array $blockIndex = [];

    #[Reactive]
    public array $selectedBlockIds = [];

    public function render(): View
    {
        return view()->file(__DIR__.'/section-tree.blade.php');
    }
}
