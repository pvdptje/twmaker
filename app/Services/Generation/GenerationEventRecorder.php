<?php

namespace App\Services\Generation;

use App\Events\GenerationEventBroadcast;
use App\Models\GenerationEvent;
use App\Models\Page;
use App\Services\Ids\IdGenerator;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerationEventRecorder
{
    public function __construct(private readonly IdGenerator $ids) {}

    public function record(
        Page $page,
        string $kind,
        string $stage,
        string $level,
        string $summary,
        ?string $targetId = null,
        ?array $payload = null,
    ): GenerationEvent {
        $summary = $this->scrubText($summary);
        $targetId = is_string($targetId) ? $this->scrubText($targetId) : null;
        $payload = is_array($payload) ? $this->scrubArray($payload) : null;

        $event = GenerationEvent::query()->create([
            'id' => $this->ids->generationEvent(),
            'page_id' => $page->id,
            'kind' => $kind,
            'stage' => $stage,
            'target_id' => $targetId,
            'level' => $level,
            'summary' => $summary,
            'payload' => $payload,
            'occurred_at' => now('UTC'),
        ]);

        try {
            broadcast(new GenerationEventBroadcast($event))->toOthers();
        } catch (Throwable $exception) {
            Log::warning('Generation event broadcast failed.', [
                'page_id' => $page->id,
                'event_id' => $event->id,
                'kind' => $kind,
                'stage' => $stage,
                'message' => $exception->getMessage(),
            ]);
        }

        return $event;
    }

    private function scrubText(string $value): string
    {
        if (mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        return mb_scrub($value, 'UTF-8');
    }

    private function scrubArray(array $value): array
    {
        foreach ($value as $key => $item) {
            $value[$key] = match (true) {
                is_string($item) => $this->scrubText($item),
                is_array($item) => $this->scrubArray($item),
                default => $item,
            };
        }

        return $value;
    }
}
