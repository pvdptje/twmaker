<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class GenerationStreamChunk implements ShouldBroadcastNow
{
    public function __construct(
        public readonly string $pageId,
        public readonly string $stage,
        public readonly string $chunk,
        public readonly int $position,
        public readonly string $stream = 'html',
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel("pages.{$this->pageId}.generation");
    }

    public function broadcastAs(): string
    {
        return 'GenerationStreamChunk';
    }

    public function broadcastWith(): array
    {
        return [
            'page_id' => $this->pageId,
            'stage' => $this->stage,
            'chunk' => $this->scrubText($this->chunk),
            'position' => $this->position,
            'stream' => $this->stream,
        ];
    }

    private function scrubText(string $value): string
    {
        if (mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        return mb_scrub($value, 'UTF-8');
    }
}
