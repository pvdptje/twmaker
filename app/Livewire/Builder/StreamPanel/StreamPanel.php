<?php

namespace App\Livewire\Builder\StreamPanel;

use App\Models\Page;
use App\Services\Generation\GenerationStreamBuffer;
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
        $streamSnapshot = app(GenerationStreamBuffer::class)->latestSectionSnapshot($this->page->id);
        $outputSnapshot = app(GenerationStreamBuffer::class)->latestOutputSnapshot($this->page->id);
        $activeStage = $this->activeStage($streamSnapshot, $outputSnapshot);
        $statusLabel = match ($this->page->status) {
            'generating' => 'running',
            'valid' => 'valid',
            'error' => 'error',
            default => $this->generationStatus,
        };

        return view()->file(__DIR__.'/stream-panel.blade.php', [
            'statusLabel' => $statusLabel,
            'activeStage' => $activeStage,
            'autoOpenStream' => $statusLabel === 'running' && ! str_starts_with($activeStage, 'targeted_edit'),
            'events' => $this->page->generationEvents()->latest('occurred_at')->limit(12)->get(),
            'streamSnapshot' => $streamSnapshot,
            'outputSnapshot' => $outputSnapshot,
            'targetedEditPatch' => $this->latestTargetedEditPatch(),
        ]);
    }

    private function activeStage(array $streamSnapshot, array $outputSnapshot): string
    {
        if ($this->page->status !== 'generating') {
            return (string) ($streamSnapshot['stage'] ?? $this->activeStage);
        }

        $event = $this->page->generationEvents()
            ->whereIn('kind', ['edit_requested', 'stage_started'])
            ->latest('occurred_at')
            ->first();

        if (is_string($event?->stage) && $event->stage !== '') {
            $this->activeStage = $event->stage;

            return $event->stage;
        }

        if ($this->activeStage !== '') {
            return $this->activeStage;
        }

        foreach ([$streamSnapshot, $outputSnapshot] as $snapshot) {
            $stage = $snapshot['stage'] ?? null;
            if (is_string($stage) && $stage !== '') {
                return $stage;
            }
        }

        return $this->activeStage;
    }

    private function latestTargetedEditPatch(): array
    {
        if ($this->page->status !== 'valid') {
            return [];
        }

        $event = $this->page->generationEvents()
            ->where('kind', 'edit_applied')
            ->where('stage', 'targeted_edit')
            ->latest('occurred_at')
            ->first();

        $payload = is_array($event?->payload) ? $event->payload : [];
        $targetIds = $payload['target_ids'] ?? null;
        $htmlSource = $payload['html_source'] ?? null;

        if (! is_array($targetIds) || $targetIds === [] || ! is_string($htmlSource) || $htmlSource === '') {
            return [];
        }

        if (! str_contains((string) ($this->page->html_source ?? ''), $htmlSource)) {
            return [];
        }

        return [
            'eventId' => $event->id,
            'targetIds' => array_values($targetIds),
            'html' => $htmlSource,
        ];
    }
}
