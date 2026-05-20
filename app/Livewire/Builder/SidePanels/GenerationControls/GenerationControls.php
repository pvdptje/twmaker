<?php

namespace App\Livewire\Builder\SidePanels\GenerationControls;

use App\Models\Page;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class GenerationControls extends Component
{
    public Page $page;

    public string $prompt = '';

    public function mount(Page $page): void
    {
        $this->prompt = $page->prompt;
    }

    public function render(): View
    {
        return view()->file(__DIR__.'/generation-controls.blade.php');
    }
}
