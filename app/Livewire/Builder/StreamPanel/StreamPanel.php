<?php

namespace App\Livewire\Builder\StreamPanel;

use App\Models\Page;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class StreamPanel extends Component
{
    public Page $page;

    public string $generationStatus = 'idle';

    public string $activeStage = 'section_generator';

    #[On('generation-started')]
    public function generationStarted(string $pageId, ?string $stage = null): void
    {
        if ($pageId === $this->page->id && is_string($stage) && $stage !== '') {
            $this->activeStage = $stage;
        }
    }

    public function render(): View
    {
        $this->page->refresh();
        $activeStage = $this->activeStage();
        $statusLabel = match ($this->page->status) {
            'generating' => 'running',
            'valid' => 'valid',
            'error' => 'error',
            default => $this->generationStatus,
        };

        return view()->file(__DIR__.'/stream-panel.blade.php', [
            'statusLabel' => $statusLabel,
            'activeStage' => $activeStage,
            'events' => $this->page->generationEvents()
                ->latest('occurred_at')
                ->limit(20)
                ->get()
                ->map(fn ($event): array => [
                    'id' => $event->id,
                    'kind' => $event->kind,
                    'stage' => $event->stage,
                    'level' => $event->level,
                    'summary' => $event->summary,
                    'occurred_at' => $event->occurred_at?->toIso8601String(),
                ])
                ->values()
                ->all(),
        ]);
    }

    private function activeStage(): string
    {
        if ($this->page->status !== 'generating') {
            return $this->activeStage;
        }

        $event = $this->page->generationEvents()
            ->whereIn('kind', ['edit_requested', 'insert_requested', 'remove_requested', 'enhance_requested', 'stage_started'])
            ->latest('occurred_at')
            ->first();

        if (is_string($event?->stage) && $event->stage !== '') {
            $this->activeStage = $event->stage;

            return $event->stage;
        }

        return $this->activeStage;
    }
}
