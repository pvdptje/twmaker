<?php

namespace App\Livewire\Builder\StreamPanel;

use App\Models\Page;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class StreamPanel extends Component
{
    public Page $page;

    public string $generationStatus = 'idle';

    public function render(): View
    {
        return view()->file(__DIR__.'/stream-panel.blade.php');
    }
}
