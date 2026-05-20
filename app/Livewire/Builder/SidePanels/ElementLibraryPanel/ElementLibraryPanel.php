<?php

namespace App\Livewire\Builder\SidePanels\ElementLibraryPanel;

use Illuminate\Contracts\View\View;
use Livewire\Component;

class ElementLibraryPanel extends Component
{
    public array $library = [];

    public function render(): View
    {
        return view()->file(__DIR__.'/element-library-panel.blade.php');
    }
}
