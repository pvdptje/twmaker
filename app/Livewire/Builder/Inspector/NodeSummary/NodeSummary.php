<?php

namespace App\Livewire\Builder\Inspector\NodeSummary;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Reactive;
use Livewire\Component;

class NodeSummary extends Component
{
    #[Reactive]
    public ?string $selectedNodeId = null;

    public function render(): View
    {
        return view()->file(__DIR__.'/node-summary.blade.php');
    }
}
