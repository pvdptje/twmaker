<?php

namespace App\Jobs;

use App\Models\Page;
use App\Services\Generation\Pipeline;
use App\Services\Generation\RelatedPagePromptBuilder;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class GenerateRelatedPageJob implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(
        public readonly string $sourcePageId,
        public readonly string $targetPageId,
        public readonly string $brief = '',
        public readonly ?string $provider = null,
        public readonly ?string $model = null,
        public readonly ?string $apiKey = null,
    ) {}

    public function handle(Pipeline $pipeline, RelatedPagePromptBuilder $prompts): void
    {
        try {
            $sourcePage = Page::query()->findOrFail($this->sourcePageId);
            $targetPage = Page::query()->findOrFail($this->targetPageId);

            $pipeline->generate(
                $targetPage,
                $this->provider,
                $this->model,
                $this->apiKey,
                [],
                $prompts->build($sourcePage, $targetPage, $this->brief),
            );
        } catch (Throwable $exception) {
            // Pipeline records generation failures once it starts. Keep sync queue
            // mode from surfacing a Livewire 500 overlay.
            report($exception);
        }
    }
}
