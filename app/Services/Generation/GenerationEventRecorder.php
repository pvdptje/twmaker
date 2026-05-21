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
}
