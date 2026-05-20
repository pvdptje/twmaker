<?php

namespace App\Livewire\Builder\Inspector\NodeSummary;

use Illuminate\Contracts\View\View;
use Livewire\Component;

class NodeSummary extends Component
{
    public array $document = [];

    public ?string $selectedNodeId = null;

    public function render(): View
    {
        return view()->file(__DIR__.'/node-summary.blade.php');
    }
}
