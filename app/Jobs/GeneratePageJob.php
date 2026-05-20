<?php

namespace App\Jobs;

use App\Models\Page;
use App\Services\Generation\Pipeline;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GeneratePageJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $pageId) {}

    public function handle(Pipeline $pipeline): void
    {
        $pipeline->generate(Page::query()->findOrFail($this->pageId));
    }
}
