<?php

namespace App\Livewire\Builder\RightInspector;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Reactive;
use Livewire\Component;

class RightInspector extends Component
{
    #[Reactive]
    public ?string $selectedNodeId = null;

    public function render(): View
    {
        return view()->file(__DIR__.'/right-inspector.blade.php');
    }
}
