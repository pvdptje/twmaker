<?php

namespace App\Services\Generation;

use Illuminate\Support\Facades\Cache;

class GenerationStreamBuffer
{
    private const TTL_SECONDS = 3600;

    private const PERSIST_INTERVAL_SECONDS = 1.0;

    private const STAGES = [
        'section_generator',
        'section_generator_retry',
        'targeted_edit',
        'section_inserter',
    ];

    /**
     * @var array<string, array<string, mixed>>
     */
    private array $snapshots = [];

    /**
     * @var array<string, float>
     */
    private array $lastPersistedAt = [];

    public function reset(string $pageId, string $stage): void
    {
        $key = $this->key($pageId, $stage);
        $snapshot = [
            'stage' => $stage,
            'html' => '',
            'position' => 0,
            'updated_at' => now()->toISOString(),
        ];

        $this->snapshots[$key] = $snapshot;
        $this->persist($key, $snapshot);
    }

    public function resetOutput(string $pageId, string $stage): void
    {
        $key = $this->outputKey($pageId, $stage);
        $snapshot = [
            'stage' => $stage,
            'output' => '',
            'position' => 0,
            'updated_at' => now()->toISOString(),
        ];

        $this->snapshots[$key] = $snapshot;
        $this->persist($key, $snapshot);
    }

    public function resetRun(string $pageId, string $stage): void
    {
        foreach (self::STAGES as $knownStage) {
            if ($knownStage === $stage) {
                $this->reset($pageId, $knownStage);
                $this->resetOutput($pageId, $knownStage);

                continue;
            }

            $this->forget($this->key($pageId, $knownStage));
            $this->forget($this->outputKey($pageId, $knownStage));
        }
    }

    public function append(string $pageId, string $stage, string $chunk, int $position): array
    {
        $key = $this->key($pageId, $stage);
        $snapshot = $this->snapshot($pageId, $stage);

        if ($position < strlen($snapshot['html'])) {
            $snapshot['html'] = substr($snapshot['html'], 0, $position).$chunk;
        } else {
            $snapshot['html'] .= $chunk;
        }

        $snapshot['position'] = strlen($snapshot['html']);
        $snapshot['updated_at'] = now()->toISOString();
        $this->snapshots[$key] = $snapshot;

        $this->persistIfDue($key, $snapshot);

        return $snapshot;
    }

    public function appendOutput(string $pageId, string $stage, string $chunk, int $position): array
    {
        $key = $this->outputKey($pageId, $stage);
        $snapshot = $this->outputSnapshot($pageId, $stage);

        if ($position < strlen($snapshot['output'])) {
            $snapshot['output'] = substr($snapshot['output'], 0, $position).$chunk;
        } else {
            $snapshot['output'] .= $chunk;
        }

        $snapshot['position'] = strlen($snapshot['output']);
        $snapshot['updated_at'] = now()->toISOString();
        $this->snapshots[$key] = $snapshot;

        $this->persistIfDue($key, $snapshot);

        return $snapshot;
    }

    public function flush(string $pageId, string $stage): void
    {
        $this->flushKey($this->key($pageId, $stage));
    }

    public function flushOutput(string $pageId, string $stage): void
    {
        $this->flushKey($this->outputKey($pageId, $stage));
    }

    public function flushRun(string $pageId, string $stage): void
    {
        $this->flush($pageId, $stage);
        $this->flushOutput($pageId, $stage);
    }

    public function snapshot(string $pageId, string $stage): array
    {
        $key = $this->key($pageId, $stage);
        $snapshot = $this->snapshots[$key] ?? Cache::get($key);

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
        foreach (['section_inserter', 'targeted_edit', 'section_generator_retry', 'section_generator'] as $stage) {
            $snapshot = $this->snapshot($pageId, $stage);

            if ($snapshot['html'] !== '') {
                return $snapshot;
            }
        }

        return $this->snapshot($pageId, 'section_generator');
    }

    public function outputSnapshot(string $pageId, string $stage): array
    {
        $key = $this->outputKey($pageId, $stage);
        $snapshot = $this->snapshots[$key] ?? Cache::get($key);

        if (! is_array($snapshot)) {
            return [
                'stage' => $stage,
                'output' => '',
                'position' => 0,
                'updated_at' => null,
            ];
        }

        return [
            'stage' => (string) ($snapshot['stage'] ?? $stage),
            'output' => (string) ($snapshot['output'] ?? ''),
            'position' => (int) ($snapshot['position'] ?? strlen((string) ($snapshot['output'] ?? ''))),
            'updated_at' => $snapshot['updated_at'] ?? null,
        ];
    }

    public function latestOutputSnapshot(string $pageId): array
    {
        foreach (['section_inserter', 'targeted_edit', 'section_generator_retry', 'section_generator'] as $stage) {
            $snapshot = $this->outputSnapshot($pageId, $stage);

            if ($snapshot['output'] !== '') {
                return $snapshot;
            }
        }

        return $this->outputSnapshot($pageId, 'section_generator');
    }

    private function key(string $pageId, string $stage): string
    {
        return "generation-stream:{$pageId}:{$stage}";
    }

    private function outputKey(string $pageId, string $stage): string
    {
        return "generation-output-stream:{$pageId}:{$stage}";
    }

    private function persistIfDue(string $key, array $snapshot): void
    {
        $now = microtime(true);
        $lastPersistedAt = $this->lastPersistedAt[$key] ?? 0.0;

        if ($now - $lastPersistedAt < self::PERSIST_INTERVAL_SECONDS) {
            return;
        }

        $this->persist($key, $snapshot, $now);
    }

    private function persist(string $key, array $snapshot, ?float $now = null): void
    {
        Cache::put($key, $snapshot, self::TTL_SECONDS);
        $this->lastPersistedAt[$key] = $now ?? microtime(true);
    }

    private function flushKey(string $key): void
    {
        if (isset($this->snapshots[$key])) {
            $this->persist($key, $this->snapshots[$key]);
        }
    }

    private function forget(string $key): void
    {
        unset($this->snapshots[$key], $this->lastPersistedAt[$key]);
        Cache::forget($key);
    }
}
