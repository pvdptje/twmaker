<?php

namespace App\Livewire\Builder\StreamPanel\EventList;

use App\Models\Page;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class EventList extends Component
{
    public Page $page;

    public function render(): View
    {
        return view()->file(__DIR__.'/event-list.blade.php', [
            'events' => $this->page->generationEvents()->latest('occurred_at')->limit(20)->get(),
        ]);
    }
}
