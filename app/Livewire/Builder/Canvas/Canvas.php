<?php

namespace App\Livewire\Builder\Canvas;

use App\Models\Page;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Canvas extends Component
{
    public Page $page;

    public array $document = [];

    public function render(): View
    {
        return view()->file(__DIR__.'/canvas.blade.php');
    }
}
