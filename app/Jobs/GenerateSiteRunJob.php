<?php

namespace App\Jobs;

use App\Models\Page;
use App\Models\SiteGenerationRun;
use App\Models\SiteGenerationRunPage;
use App\Services\Generation\GenerationEventRecorder;
use App\Services\Generation\Pipeline;
use App\Services\Generation\RelatedPagePromptBuilder;
use App\Services\Generation\SiteZipFinalizer;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Throwable;

class GenerateSiteRunJob implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 3600;

    public function __construct(
        public readonly string $siteGenerationRunId,
        public readonly ?string $provider = null,
        public readonly ?string $model = null,
        public readonly ?string $apiKey = null,
    ) {}

    public function handle(
        Pipeline $pipeline,
        RelatedPagePromptBuilder $prompts,
        SiteZipFinalizer $zipFinalizer,
        GenerationEventRecorder $events,
    ): void {
        $run = SiteGenerationRun::query()
            ->with(['sourcePage', 'pages.targetPage'])
            ->findOrFail($this->siteGenerationRunId);

        $run->forceFill([
            'status' => 'running',
            'started_at' => $run->started_at ?? now('UTC'),
            'error_message' => null,
        ])->save();

        try {
            $sourcePage = $run->sourcePage;

            if (! $sourcePage instanceof Page) {
                throw new \RuntimeException('Source page is missing.');
            }

            $siteMap = $this->siteMap($run);
            $failed = false;

            foreach ($run->pages as $runPage) {
                if (! $runPage instanceof SiteGenerationRunPage || ! $runPage->targetPage instanceof Page) {
                    $failed = true;

                    continue;
                }

                $runPage->forceFill([
                    'status' => 'generating',
                    'error_message' => null,
                ])->save();

                $targetPage = $runPage->targetPage;
                $targetPage->forceFill(['status' => 'generating'])->save();

                $events->record(
                    $targetPage,
                    'site_page_requested',
                    'section_generator',
                    'info',
                    'Creating a generated-site page from '.$sourcePage->name.'.',
                    payload: [
                        'site_generation_run_id' => $run->id,
                        'source_page_id' => $sourcePage->id,
                        'source_page_name' => $sourcePage->name,
                        'brief' => $runPage->brief,
                    ],
                );

                try {
                    $pipeline->generate(
                        $targetPage,
                        $this->provider,
                        $this->model,
                        $this->apiKey,
                        [],
                        $prompts->buildForSiteRun($sourcePage, $targetPage, $runPage->brief, $siteMap),
                    );

                    $runPage->forceFill([
                        'status' => 'completed',
                        'error_message' => null,
                    ])->save();
                } catch (Throwable $exception) {
                    report($exception);

                    $failed = true;
                    $runPage->forceFill([
                        'status' => 'failed',
                        'error_message' => Str::limit($exception->getMessage(), 1000, ''),
                    ])->save();
                }
            }

            if ($failed) {
                $run->forceFill([
                    'status' => 'failed',
                    'error_message' => 'One or more generated-site pages failed.',
                    'completed_at' => now('UTC'),
                    'generated_page_ids' => $run->pages()
                        ->where('status', 'completed')
                        ->pluck('target_page_id')
                        ->filter()
                        ->values()
                        ->all(),
                ])->save();

                return;
            }

            $zipFinalizer->finalize($run->refresh());
        } catch (Throwable $exception) {
            report($exception);

            $run->forceFill([
                'status' => 'failed',
                'error_message' => Str::limit($exception->getMessage(), 1000, ''),
                'completed_at' => now('UTC'),
            ])->save();
        }
    }

    /**
     * @return array<int, array{name: string, filename: string, current?: bool}>
     */
    private function siteMap(SiteGenerationRun $run): array
    {
        $used = [];
        $sourcePage = $run->sourcePage;
        $map = [];

        if ($sourcePage instanceof Page) {
            $map[] = [
                'name' => $sourcePage->name,
                'filename' => $this->uniqueFilename($sourcePage->name, $used),
            ];
        }

        foreach ($run->pages as $runPage) {
            $map[] = [
                'name' => $runPage->name,
                'filename' => $this->uniqueFilename($runPage->slug ?: $runPage->name, $used),
            ];
        }

        return $map;
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
}
