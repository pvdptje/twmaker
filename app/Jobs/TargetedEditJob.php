<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class TargetedEditJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $pageId,
        public readonly string $targetId,
        public readonly string $instruction,
    ) {}

    public function handle(): void
    {
        // M5 wires targeted editing. This placeholder reserves the queued contract.
    }
}
