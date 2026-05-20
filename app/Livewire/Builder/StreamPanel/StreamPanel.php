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
        $this->page->refresh();

        return view()->file(__DIR__.'/stream-panel.blade.php', [
            'statusLabel' => match ($this->page->status) {
                'generating' => 'running',
                'valid' => 'valid',
                'error' => 'error',
                default => $this->generationStatus,
            },
        ]);
    }
}
