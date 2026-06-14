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
            'payload' => $this->broadcastPayload(),
            'occurred_at' => $this->event->occurred_at?->toISOString(),
        ];
    }

    /**
     * Keep realtime events below broadcaster payload limits. The full payload,
     * including prompt logs, remains stored on the GenerationEvent row.
     *
     * @return array<string, mixed>|null
     */
    private function broadcastPayload(): ?array
    {
        $payload = $this->event->payload;

        if (! is_array($payload)) {
            return null;
        }

        if (array_key_exists('prompt_log', $payload)) {
            unset($payload['prompt_log']);
            $payload['prompt_log_available'] = true;
        }

        if (is_string($payload['html_source'] ?? null) && strlen($payload['html_source']) > 7_000) {
            unset($payload['html_source']);
            $payload['html_source_available'] = true;
        }

        return $payload;
    }
}
