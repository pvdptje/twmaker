<?php

namespace App\Livewire\Builder\SidePanels\SectionTree;

use Illuminate\Contracts\View\View;
use Livewire\Component;

class SectionTree extends Component
{
    public array $blockIndex = [];

    public function render(): View
    {
        return view()->file(__DIR__.'/section-tree.blade.php');
    }
}
