<?php

namespace App\Livewire\Builder\Inspector\EditForm;

use Illuminate\Contracts\View\View;
use Livewire\Component;

class EditForm extends Component
{
    public ?string $selectedNodeId = null;

    public string $instruction = '';

    public function render(): View
    {
        return view()->file(__DIR__.'/edit-form.blade.php');
    }
}
