<?php

namespace App\Events;

use App\Models\GenerationEvent;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Queue\SerializesModels;

class GenerationEventBroadcast implements ShouldBroadcastNow
{
    use SerializesModels;

    public function __construct(public GenerationEvent $event) {}

    public function broadcastOn(): Channel
    {
        return new Channel("pages.{$this->event->page_id}.generation");
    }

    public function broadcastAs(): string
    {
        return 'GenerationEventBroadcast';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->event->id,
            'page_id' => $this->event->page_id,
            'kind' => $this->event->kind,
            'stage' => $this->event->stage,
            'target_id' => $this->event->target_id,
            'level' => $this->event->level,
            'summary' => $this->event->summary,
            'payload' => $this->event->payload,
            'occurred_at' => $this->event->occurred_at?->toISOString(),
        ];
    }
}
