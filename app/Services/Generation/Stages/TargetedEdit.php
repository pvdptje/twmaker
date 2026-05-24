<?php

namespace App\Services\Generation\Stages;

use App\Events\GenerationStreamChunk;
use App\Models\Page;
use App\Services\Generation\GenerationStreamBuffer;
use App\Services\Html\BlockIndexer;
use App\Services\Html\HtmlDocumentValidator;
use App\Services\Html\HtmlValidationException;
use App\Services\Ids\IdGenerator;
use App\Services\Llm\LlmProvider;
use App\Services\Llm\TextRequest;
use Illuminate\Support\Facades\Log;
use Throwable;

class TargetedEdit
{
    public function __construct(
        private readonly LlmProvider $provider,
        private readonly PromptBuilder $prompts,
        private readonly BlockIndexer $blocks,
        private readonly HtmlDocumentValidator $validator,
        private readonly IdGenerator $ids,
        private readonly GenerationStreamBuffer $streamBuffer,
    ) {}

    public function edit(Page $page, string $targetId, string $instruction, ?string $provider = null, ?string $model = null, ?string $apiKey = null): array
    {
        return $this->editMany($page, [$targetId], $instruction, $provider, $model, $apiKey);
    }

    /**
     * @param  array<int, string>  $targetIds
     */
    public function editMany(Page $page, array $targetIds, string $instruction, ?string $provider = null, ?string $model = null, ?string $apiKey = null): array
    {
        $provider ??= (string) config('llm.default_provider', 'anthropic');
        $targetIds = $this->normalizeTargetIds($targetIds);
        $htmlSource = (string) ($page->html_source ?? '');
        $blockIndex = $this->blocks->index($htmlSource);
        $targetBlocks = $this->targetBlocks($blockIndex, $targetIds);

        $this->assertContiguous($targetBlocks);

        $stage = 'targeted_edit';
        $this->streamBuffer->resetRun($page->id, $stage);

        $request = new TextRequest(
            stage: $stage,
            provider: $provider,
            model: $model ?: (string) config("llm.providers.{$provider}.models.targeted_edit"),
            systemPrompt: $this->prompts->system('targeted_edit'),
            userPrompt: $this->buildUserPrompt($instruction, $targetIds, $targetBlocks, $htmlSource, $blockIndex),
            context: [
                'page_id' => $page->id,
                'page_name' => $page->name,
                'target_ids' => implode(',', $targetIds),
            ],
            maxTokens: (int) config("llm.providers.{$provider}.edit_max_tokens", 8000),
            temperature: 0.4,
            apiKey: $apiKey,
        );

        try {
            if (! method_exists($this->provider, 'sendTextStream')) {
                throw new \RuntimeException('The configured LLM provider does not support plain text streaming.');
            }

            $response = $this->provider->sendTextStream($request, $this->streamHtml($page, $stage));
        } finally {
            $this->streamBuffer->flushRun($page->id, $stage);
        }

        $replacement = $this->normalizeReplacementIds(
            $this->stripCodeFence(trim((string) $response->text)),
            $targetIds[0],
        );

        $this->validator->assertValid($replacement);

        return [
            'html_source' => $replacement,
            'explanation' => count($targetIds) > 1 ? 'Edited selected block range.' : 'Edited selected block.',
            'blocks' => $this->compactBlockIndex($this->blocks->index($replacement)),
            '_llm' => [
                'provider' => $provider,
                'model' => $response->model,
                'usage' => $response->usage,
            ],
        ];
    }

    private function streamHtml(Page $page, string $stage): callable
    {
        return function (string $chunk, int $position) use ($page, $stage): void {
            if ($chunk === '') {
                return;
            }

            Log::debug('Broadcasting targeted edit stream chunk.', [
                'page_id' => $page->id,
                'stage' => $stage,
                'position' => $position,
                'bytes' => strlen($chunk),
            ]);

            $this->streamBuffer->append($page->id, $stage, $chunk, $position);

            $this->broadcastChunk(new GenerationStreamChunk($page->id, $stage, $chunk, $position), $page);
        };
    }

    private function broadcastChunk(GenerationStreamChunk $event, Page $page): void
    {
        try {
            broadcast($event);
        } catch (Throwable $exception) {
            Log::warning('Generation stream broadcast failed.', [
                'page_id' => $page->id,
                'stage' => $event->stage,
                'stream' => $event->stream,
                'position' => $event->position,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<int, string>  $targetIds
     * @param  array<int, array<string, mixed>>  $targetBlocks
     * @param  array<int, array<string, mixed>>  $blockIndex
     */
    private function buildUserPrompt(string $instruction, array $targetIds, array $targetBlocks, string $htmlSource, array $blockIndex): string
    {
        $targetHtml = $this->targetHtml($htmlSource, $targetBlocks);
        $surrounding = $this->surroundingHtml($htmlSource, $targetBlocks);
        $compact = $this->compactBlockIndex($blockIndex);
        $blockList = '';

        foreach ($compact as $block) {
            $blockList .= "- {$block['id']} ({$block['type']}, \"{$block['label']}\")\n";
        }

        $rangeNote = count($targetIds) === 1
            ? 'Return one or more complete tw:block regions as the replacement.'
            : 'Return one or more complete tw:block regions that replace the selected contiguous block range. The first returned block keeps the first selected block identity; extra returned blocks become new sections.';

        return implode("\n\n", array_filter([
            "User instruction:\n{$instruction}",
            "Selected block ids: ".implode(', ', $targetIds),
            "All block ids in the page:\n".trim($blockList),
            "Selected block HTML (including markers):\n{$targetHtml}",
            "Surrounding HTML for context:\n{$surrounding}",
            $rangeNote,
        ]));
    }

    private function compactBlockIndex(array $blocks): array
    {
        return array_map(
            fn (array $block): array => [
                'id' => (string) ($block['id'] ?? ''),
                'type' => (string) ($block['type'] ?? 'custom'),
                'label' => (string) ($block['label'] ?? 'Block'),
                'summary' => (string) ($block['summary'] ?? ''),
            ],
            $blocks,
        );
    }

    private function surroundingHtml(string $htmlSource, array $targetBlocks): string
    {
        $start = max(0, (int) $targetBlocks[0]['start_offset'] - 2500);
        $end = min(strlen($htmlSource), (int) $targetBlocks[count($targetBlocks) - 1]['end_offset'] + 2500);

        return substr($htmlSource, $start, $end - $start);
    }

    /**
     * @param  array<int, string>  $targetIds
     * @return array<int, string>
     */
    private function normalizeTargetIds(array $targetIds): array
    {
        $targetIds = array_values(array_unique(array_filter(
            $targetIds,
            fn (mixed $id): bool => is_string($id) && $id !== '',
        )));

        if ($targetIds === []) {
            throw new HtmlValidationException(['At least one block must be selected.']);
        }

        return $targetIds;
    }

    /**
     * @param  array<int, array{id: string, type: string, label: string, start_offset: int, end_offset: int, html: string, summary: string|null}>  $blockIndex
     * @param  array<int, string>  $targetIds
     * @return array<int, array{id: string, type: string, label: string, start_offset: int, end_offset: int, html: string, summary: string|null, position: int}>
     */
    private function targetBlocks(array $blockIndex, array $targetIds): array
    {
        $wanted = array_flip($targetIds);
        $targetBlocks = [];

        foreach ($blockIndex as $position => $block) {
            if (isset($wanted[$block['id']])) {
                $block['position'] = $position;
                $targetBlocks[] = $block;
            }
        }

        $missing = array_values(array_diff($targetIds, array_column($targetBlocks, 'id')));
        if ($missing !== []) {
            throw new HtmlValidationException(['Block ['.implode(', ', $missing).'] was not found.']);
        }

        return $targetBlocks;
    }

    private function assertContiguous(array $targetBlocks): void
    {
        if (count($targetBlocks) < 2) {
            return;
        }

        $positions = array_column($targetBlocks, 'position');
        if (($positions[count($positions) - 1] - $positions[0] + 1) !== count($positions)) {
            throw new HtmlValidationException(['Selected blocks must be contiguous.']);
        }
    }

    private function targetHtml(string $htmlSource, array $targetBlocks): string
    {
        $first = $targetBlocks[0];
        $last = $targetBlocks[count($targetBlocks) - 1];

        return substr($htmlSource, (int) $first['start_offset'], (int) $last['end_offset'] - (int) $first['start_offset']);
    }

    private function stripCodeFence(string $text): string
    {
        if (preg_match('/^```(?:html)?\s*(.*?)\s*```$/is', $text, $matches)) {
            return trim($matches[1]);
        }

        $text = preg_replace('/^\s*```[A-Za-z0-9_-]*[ \t]*(?:\R|$)/', '', $text, 1) ?? $text;
        $text = preg_replace('/(?:\R|^)```[ \t]*$/', '', $text, 1) ?? $text;

        return trim($text);
    }

    private function normalizeReplacementIds(string $replacement, string $targetId): string
    {
        $blocks = $this->blocks->index($replacement);

        foreach (array_reverse($blocks) as $index => $block) {
            $oldId = (string) $block['id'];
            $newId = $index === count($blocks) - 1 ? $targetId : $this->ids->section();

            if ($oldId === '' || $oldId === $newId) {
                continue;
            }

            $blockHtml = substr($replacement, $block['start_offset'], $block['end_offset'] - $block['start_offset']);
            $blockHtml = str_replace(
                [
                    'id="'.$oldId.'"',
                    "id='{$oldId}'",
                ],
                [
                    'id="'.$newId.'"',
                    "id='{$newId}'",
                ],
                $blockHtml,
            );

            $replacement = substr($replacement, 0, $block['start_offset'])
                .$blockHtml
                .substr($replacement, $block['end_offset']);
        }

        return $replacement;
    }
}
