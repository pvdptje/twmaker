<?php

namespace App\Livewire\Builder\Inspector\LockToggles;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Reactive;
use Livewire\Component;

class LockToggles extends Component
{
    #[Reactive]
    public ?string $selectedNodeId = null;

    public bool $contentLocked = false;

    public bool $styleLocked = false;

    public bool $layoutLocked = false;

    public function render(): View
    {
        return view()->file(__DIR__.'/lock-toggles.blade.php');
    }
}
