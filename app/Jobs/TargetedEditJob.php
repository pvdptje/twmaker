<?php

namespace App\Jobs;

use App\Models\Page;
use App\Services\Generation\Pipeline;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class TargetedEditJob implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(
        public readonly string $pageId,
        public readonly string|array $targetId,
        public readonly string $instruction,
        public readonly ?string $provider = null,
        public readonly ?string $model = null,
        public readonly ?string $apiKey = null,
        public readonly array $images = [],
    ) {}

    public function handle(Pipeline $pipeline): void
    {
        try {
            $targetIds = is_array($this->targetId) ? $this->targetId : [$this->targetId];

            $pipeline->editMany(
                Page::query()->findOrFail($this->pageId),
                $targetIds,
                $this->instruction,
                $this->provider,
                $this->model,
                $this->apiKey,
                $this->images,
            );
        } catch (Throwable $exception) {
            // Pipeline records edit_rejected. Keep sync queue mode from surfacing a Livewire 500 overlay.
            report($exception);
        }
    }
}
