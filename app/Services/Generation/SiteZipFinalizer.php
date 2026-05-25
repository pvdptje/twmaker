<?php

namespace App\Services\Generation;

use App\Models\Page;
use App\Models\SiteGenerationRun;
use App\Models\SiteGenerationRunPage;
use App\Services\Rendering\Renderer;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

class SiteZipFinalizer
{
    public function __construct(private readonly Renderer $renderer) {}

    public function finalize(SiteGenerationRun $run): SiteGenerationRun
    {
        $run->loadMissing(['project', 'sourcePage', 'pages.targetPage']);

        $incomplete = $run->pages->first(fn (SiteGenerationRunPage $page): bool => $page->status !== 'completed');

        if ($incomplete instanceof SiteGenerationRunPage) {
            return $this->fail($run, 'One or more generated pages did not complete.');
        }

        $exportPages = $this->exportPages($run);

        if ($exportPages === []) {
            return $this->fail($run, 'No generated pages were available for the zip.');
        }

        $filename = $this->zipFilename($run);
        $zipPath = "site-runs/{$run->id}/{$filename}";
        $temporaryPath = tempnam(sys_get_temp_dir(), 'twmaker-site-');

        if ($temporaryPath === false) {
            return $this->fail($run, 'Could not create a temporary zip file.');
        }

        $zip = new ZipArchive;

        if ($zip->open($temporaryPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            @unlink($temporaryPath);

            return $this->fail($run, 'Could not open the temporary zip file.');
        }

        foreach ($exportPages as $exportPage) {
            $zip->addFromString(
                $exportPage['filename'],
                $this->renderer->renderDownloadHtml($exportPage['html'], $exportPage['title']),
            );
        }

        $zip->close();

        $bytes = file_get_contents($temporaryPath);

        if ($bytes === false) {
            @unlink($temporaryPath);

            return $this->fail($run, 'Could not read the generated zip file.');
        }

        $disk = $run->zip_disk ?: 'local';

        if (! Storage::disk($disk)->put($zipPath, $bytes)) {
            @unlink($temporaryPath);

            return $this->fail($run, 'Could not store the generated zip file.');
        }

        @unlink($temporaryPath);

        $run->forceFill([
            'status' => 'completed',
            'zip_disk' => $disk,
            'zip_path' => $zipPath,
            'zip_filename' => $filename,
            'zip_byte_size' => strlen($bytes),
            'error_message' => null,
            'completed_at' => now('UTC'),
            'generated_page_ids' => $run->pages
                ->pluck('target_page_id')
                ->filter()
                ->values()
                ->all(),
        ])->save();

        return $run;
    }

    /**
     * @return array<int, array{title: string, filename: string, html: string}>
     */
    private function exportPages(SiteGenerationRun $run): array
    {
        $used = [];
        $pages = [];

        $sourcePage = $run->sourcePage;

        if (! $sourcePage instanceof Page || trim((string) ($sourcePage->html_source ?? '')) === '') {
            throw new RuntimeException('Source page has no HTML to export.');
        }

        $pages[] = [
            'title' => $sourcePage->name,
            'filename' => $this->uniqueFilename(Str::slug($sourcePage->name) ?: 'index', $used),
            'html' => (string) $sourcePage->html_source,
        ];

        foreach ($run->pages as $runPage) {
            $targetPage = $runPage->targetPage;

            if (! $targetPage instanceof Page || trim((string) ($targetPage->html_source ?? '')) === '') {
                return [];
            }

            $pages[] = [
                'title' => $targetPage->name,
                'filename' => $this->uniqueFilename($runPage->slug ?: $targetPage->name, $used),
                'html' => (string) $targetPage->html_source,
            ];
        }

        return $pages;
    }

    /**
     * @param  array<string, true>  $used
     */
    private function uniqueFilename(string $base, array &$used): string
    {
        $base = Str::slug($base) ?: 'page';
        $filename = "{$base}.html";
        $suffix = 2;

        while (isset($used[$filename])) {
            $filename = "{$base}-{$suffix}.html";
            $suffix++;
        }

        $used[$filename] = true;

        return $filename;
    }

    private function zipFilename(SiteGenerationRun $run): string
    {
        $project = Str::slug((string) ($run->project?->name ?? 'site')) ?: 'site';
        $source = Str::slug((string) ($run->sourcePage?->name ?? 'source')) ?: 'source';

        return "{$project}-{$source}-site.zip";
    }

    private function fail(SiteGenerationRun $run, string $message): SiteGenerationRun
    {
        $run->forceFill([
            'status' => 'failed',
            'error_message' => $message,
            'completed_at' => now('UTC'),
        ])->save();

        return $run;
    }
}
