<?php

namespace App\Jobs;

use App\Models\Page;
use App\Services\Generation\Pipeline;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class GeneratePageJob implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(
        public readonly string $pageId,
        public readonly ?string $provider = null,
        public readonly ?string $model = null,
        public readonly ?string $apiKey = null,
        public readonly array $images = [],
    ) {}

    public function handle(Pipeline $pipeline): void
    {
        try {
            $pipeline->generate(Page::query()->findOrFail($this->pageId), $this->provider, $this->model, $this->apiKey, $this->images);
        } catch (Throwable $exception) {
            // Pipeline records the terminal generation_failed event and page status.
            // Swallow here so sync queue mode does not surface a Livewire 500 overlay.
            report($exception);
        }
    }
}
