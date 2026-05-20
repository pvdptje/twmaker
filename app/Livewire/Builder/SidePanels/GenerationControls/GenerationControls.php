<?php

namespace App\Livewire\Builder\SidePanels\GenerationControls;

use App\Jobs\GeneratePageJob;
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

    public function generate(): void
    {
        $this->validate([
            'prompt' => ['required', 'string', 'min:3', 'max:5000'],
        ]);

        $this->page->forceFill([
            'prompt' => $this->prompt,
            'status' => 'generating',
        ])->save();

        $this->dispatch('generation-started', pageId: $this->page->id);

        GeneratePageJob::dispatch($this->page->id);

        $this->dispatch('generation-finished', pageId: $this->page->id, status: $this->page->refresh()->status);
    }

    public function render(): View
    {
        return view()->file(__DIR__.'/generation-controls.blade.php');
    }
}
