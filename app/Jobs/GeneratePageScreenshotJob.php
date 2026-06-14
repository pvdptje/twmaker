<?php

namespace App\Jobs;

use App\Models\Page;
use App\Services\Screenshots\PageScreenshotService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class GeneratePageScreenshotJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 180;

    public function __construct(public readonly string $pageId) {}

    public function handle(PageScreenshotService $service): void
    {
        $page = Page::query()->find($this->pageId);

        if (! $page instanceof Page) {
            return;
        }

        $page->forceFill(['screenshot_status' => 'processing'])->save();

        try {
            $service->capture($page);
        } catch (Throwable $exception) {
            report($exception);

            $page->forceFill(['screenshot_status' => 'failed'])->save();
        }
    }

    public function failed(?Throwable $exception): void
    {
        Page::query()->whereKey($this->pageId)->update(['screenshot_status' => 'failed']);
    }
}
