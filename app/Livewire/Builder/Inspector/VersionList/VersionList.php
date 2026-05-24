<?php

namespace App\Livewire\Builder\Inspector\VersionList;

use App\Models\Page;
use App\Models\PageVersion;
use App\Services\Rendering\Renderer;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class VersionList extends Component
{
    public Page $page;

    public string $activeVersionId = '';

    public function render(): View
    {
        $versions = $this->page->versions()
            ->orderByDesc('created_at')
            ->limit(50)
            ->get(['id', 'created_by_kind', 'summary', 'created_at']);

        if ($this->activeVersionId === '' && $versions->isNotEmpty()) {
            $this->activeVersionId = (string) $versions->first()->id;
        }

        return view()->file(__DIR__.'/version-list.blade.php', [
            'versions' => $versions->map(fn (PageVersion $version): array => [
                'id' => (string) $version->id,
                'kind' => (string) $version->created_by_kind,
                'summary' => (string) ($version->summary ?: $this->defaultSummary($version)),
                'created_at' => $version->created_at?->diffForHumans() ?: '',
            ])->all(),
        ]);
    }

    public function restore(string $versionId): void
    {
        $version = $this->page->versions()->whereKey($versionId)->first();

        if (! $version || ! is_string($version->html_source) || trim($version->html_source) === '') {
            return;
        }

        $this->page->forceFill([
            'html_source' => $version->html_source,
            'rendered_html_cache' => app(Renderer::class)->renderPreviewHtml($version->html_source, $this->page->name),
            'status' => 'valid',
        ])->save();

        $this->activeVersionId = (string) $version->id;

        $this->dispatch('generation-finished', pageId: $this->page->id, status: 'valid', incremental: false);
    }

    #[On('generation-finished')]
    public function refreshOnGenerationFinish(string $pageId, string $status = 'valid', bool $incremental = false): void
    {
        if ($pageId !== $this->page->id) {
            return;
        }

        $latest = $this->page->versions()->orderByDesc('created_at')->first(['id']);

        if ($latest) {
            $this->activeVersionId = (string) $latest->id;
        }
    }

    private function defaultSummary(PageVersion $version): string
    {
        return match ($version->created_by_kind) {
            'generation' => 'Initial generation',
            'edit' => 'Targeted edit',
            default => 'Snapshot',
        };
    }
}
