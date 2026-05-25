<?php

namespace App\Jobs;

use App\Models\Page;
use App\Services\Generation\Pipeline;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class GranularizeBlocksJob implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(
        public readonly string $pageId,
        public readonly ?string $provider = null,
        public readonly ?string $model = null,
        public readonly ?string $apiKey = null,
    ) {}

    public function handle(Pipeline $pipeline): void
    {
        try {
            $pipeline->granularizeBlocks(
                Page::query()->findOrFail($this->pageId),
                $this->provider,
                $this->model,
                $this->apiKey,
            );
        } catch (Throwable $exception) {
            // Pipeline records granularize_rejected. Keep sync queue mode from surfacing a Livewire 500 overlay.
            report($exception);
        }
    }
}
