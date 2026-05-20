<?php

namespace App\Livewire\Builder\RightInspector;

use App\Models\Page;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Reactive;
use Livewire\Component;

class RightInspector extends Component
{
    public Page $page;

    #[Reactive]
    public ?string $selectedNodeId = null;

    public function render(): View
    {
        return view()->file(__DIR__.'/right-inspector.blade.php');
    }
}
