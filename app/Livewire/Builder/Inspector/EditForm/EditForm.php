<?php

namespace App\Livewire\Builder\Inspector\EditForm;

use App\Jobs\TargetedEditJob;
use App\Models\Page;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Reactive;
use Livewire\Component;

class EditForm extends Component
{
    public Page $page;

    #[Reactive]
    public ?string $selectedNodeId = null;

    public string $instruction = '';

    public function applyEdit(): void
    {
        $this->validate([
            'selectedNodeId' => ['required', 'string'],
            'instruction' => ['required', 'string', 'min:3', 'max:5000'],
        ]);

        $this->page->forceFill(['status' => 'generating'])->save();
        $this->dispatch('generation-started', pageId: $this->page->id);

        TargetedEditJob::dispatch($this->page->id, (string) $this->selectedNodeId, $this->instruction);
        $this->instruction = '';
    }

    public function render(): View
    {
        return view()->file(__DIR__.'/edit-form.blade.php');
    }
}
