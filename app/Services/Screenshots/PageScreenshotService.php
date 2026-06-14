<?php

namespace App\Services\Screenshots;

use App\Models\Page;
use App\Services\Rendering\Renderer;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Spatie\Browsershot\Browsershot;

class PageScreenshotService
{
    public function __construct(private readonly Renderer $renderer) {}

    /**
     * Render the page's latest HTML and store a PNG screenshot on disk.
     */
    public function capture(Page $page): void
    {
        $htmlSource = trim((string) ($page->html_source ?? ''));

        if ($htmlSource === '') {
            throw new RuntimeException('Page has no generated HTML to screenshot.');
        }

        $html = $this->renderer->renderDownloadHtml($htmlSource, $page->name);

        $binary = $this->renderPng($html);

        $disk = $this->disk();
        $path = 'screenshots/'.$page->id.'.png';

        Storage::disk($disk)->put($path, $binary);

        $page->forceFill([
            'screenshot_path' => $path,
            'screenshot_status' => 'ready',
            'screenshot_taken_at' => now(),
        ])->save();
    }

    /**
     * Capture the rendered HTML as PNG binary using headless Chromium.
     */
    protected function renderPng(string $html): string
    {
        return Browsershot::html($html)
            ->noSandbox()
            ->windowSize(1366, 900)
            ->deviceScaleFactor(1)
            ->waitUntilNetworkIdle()
            ->setDelay(800)
            ->setScreenshotType('png')
            ->timeout(120)
            ->screenshot();
    }

    public function disk(): string
    {
        return (string) config('filesystems.default', 'local');
    }
}
