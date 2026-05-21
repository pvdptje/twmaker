<?php

namespace App\Services\Generation;

use Illuminate\Support\Facades\Cache;

class GenerationStreamBuffer
{
    private const TTL_SECONDS = 3600;

    private const STAGES = [
        'section_generator',
        'section_generator_retry',
        'targeted_edit',
    ];

    public function reset(string $pageId, string $stage): void
    {
        Cache::put($this->key($pageId, $stage), [
            'stage' => $stage,
            'html' => '',
            'position' => 0,
            'updated_at' => now()->toISOString(),
        ], self::TTL_SECONDS);
    }

    public function resetRun(string $pageId, string $stage): void
    {
        foreach (self::STAGES as $knownStage) {
            if ($knownStage === $stage) {
                $this->reset($pageId, $knownStage);

                continue;
            }

            Cache::forget($this->key($pageId, $knownStage));
        }
    }

    public function append(string $pageId, string $stage, string $chunk, int $position): array
    {
        $snapshot = $this->snapshot($pageId, $stage);

        if ($position < strlen($snapshot['html'])) {
            $snapshot['html'] = substr($snapshot['html'], 0, $position).$chunk;
        } else {
            $snapshot['html'] .= $chunk;
        }

        $snapshot['position'] = strlen($snapshot['html']);
        $snapshot['updated_at'] = now()->toISOString();

        Cache::put($this->key($pageId, $stage), $snapshot, self::TTL_SECONDS);

        return $snapshot;
    }

    public function snapshot(string $pageId, string $stage): array
    {
        $snapshot = Cache::get($this->key($pageId, $stage));

        if (! is_array($snapshot)) {
            return [
                'stage' => $stage,
                'html' => '',
                'position' => 0,
                'updated_at' => null,
            ];
        }

        return [
            'stage' => (string) ($snapshot['stage'] ?? $stage),
            'html' => (string) ($snapshot['html'] ?? ''),
            'position' => (int) ($snapshot['position'] ?? strlen((string) ($snapshot['html'] ?? ''))),
            'updated_at' => $snapshot['updated_at'] ?? null,
        ];
    }

    public function latestSectionSnapshot(string $pageId): array
    {
        foreach (['targeted_edit', 'section_generator_retry', 'section_generator'] as $stage) {
            $snapshot = $this->snapshot($pageId, $stage);

            if ($snapshot['html'] !== '') {
                return $snapshot;
            }
        }

        return $this->snapshot($pageId, 'section_generator');
    }

    private function key(string $pageId, string $stage): string
    {
        return "generation-stream:{$pageId}:{$stage}";
    }
}
