<?php

namespace App\Livewire\Builder\StreamPanel;

use App\Models\Page;
use App\Services\Generation\GenerationStreamBuffer;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class StreamPanel extends Component
{
    public Page $page;

    public string $generationStatus = 'idle';

    public function render(): View
    {
        $this->page->refresh();

        return view()->file(__DIR__.'/stream-panel.blade.php', [
            'statusLabel' => match ($this->page->status) {
                'generating' => 'running',
                'valid' => 'valid',
                'error' => 'error',
                default => $this->generationStatus,
            },
            'events' => $this->page->generationEvents()->latest('occurred_at')->limit(12)->get(),
            'streamSnapshot' => app(GenerationStreamBuffer::class)->latestSectionSnapshot($this->page->id),
            'outputSnapshot' => app(GenerationStreamBuffer::class)->latestOutputSnapshot($this->page->id),
            'targetedEditPatch' => $this->latestTargetedEditPatch(),
        ]);
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
