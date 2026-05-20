<?php

namespace App\Jobs;

use App\Models\Page;
use App\Services\Generation\Pipeline;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class TargetedEditJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(
        public readonly string $pageId,
        public readonly string $targetId,
        public readonly string $instruction,
    ) {}

    public function handle(Pipeline $pipeline): void
    {
        try {
            $pipeline->edit(
                Page::query()->findOrFail($this->pageId),
                $this->targetId,
                $this->instruction,
            );
        } catch (Throwable $exception) {
            // Pipeline records edit_rejected. Keep sync queue mode from surfacing a Livewire 500 overlay.
            report($exception);
        }
    }
}
