<?php

namespace App\Livewire\Builder\RightInspector;

use Illuminate\Contracts\View\View;
use Livewire\Component;

class RightInspector extends Component
{
    public array $document = [];

    public ?string $selectedNodeId = null;

    public function render(): View
    {
        return view()->file(__DIR__.'/right-inspector.blade.php');
    }
}
