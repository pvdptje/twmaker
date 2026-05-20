<?php

namespace App\Jobs;

use App\Models\Page;
use App\Services\Generation\Pipeline;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class GeneratePageJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $pageId) {}

    public function handle(Pipeline $pipeline): void
    {
        try {
            $pipeline->generate(Page::query()->findOrFail($this->pageId));
        } catch (Throwable) {
            // Pipeline records the terminal generation_failed event and page status.
            // Swallow here so sync queue mode does not surface a Livewire 500 overlay.
        }
    }
}
