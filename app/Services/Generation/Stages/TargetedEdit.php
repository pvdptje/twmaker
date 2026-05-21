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
use App\Services\Llm\StructuredRequest;
use Illuminate\Support\Facades\Log;

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

        $request = new StructuredRequest(
            stage: 'targeted_edit',
            provider: $provider,
            model: $model ?: (string) config("llm.providers.{$provider}.models.targeted_edit"),
            systemPrompt: $this->prompts->system('targeted_edit'),
            userPrompt: $instruction,
            toolName: 'submit_targeted_edit',
            schema: $this->schema(),
            context: [
                'page_id' => $page->id,
                'page_name' => $page->name,
                'target_id' => $targetIds[0],
                'target_ids' => $targetIds,
                'target_block' => $targetBlocks[0],
                'target_blocks' => $targetBlocks,
                'target_html' => $this->targetHtml($htmlSource, $targetBlocks),
                'block_index' => $this->compactBlockIndex($blockIndex),
                'surrounding_html' => $this->surroundingHtml($htmlSource, $targetBlocks),
                'instructions' => [
                    count($targetIds) === 1
                        ? 'Return one or more complete tw:block regions as html_source.'
                        : 'Return one or more complete tw:block regions as html_source to replace the selected contiguous block range.',
                    'The first returned block replaces the first selected block identity; extra returned blocks become new sections.',
                    'Do not include script tags, inline event handlers, or javascript: URLs.',
                ],
            ],
            maxTokens: (int) config("llm.providers.{$provider}.edit_max_tokens", 8000),
            temperature: 0.4,
            apiKey: $apiKey,
        );

        $response = method_exists($this->provider, 'sendStructuredStream')
            ? $this->provider->sendStructuredStream($request, $this->streamHtmlSource($page, $stage))
            : $this->provider->sendStructured($request);

        $replacement = $this->normalizeReplacementIds(
            (string) ($response->output['html_source'] ?? ''),
            $targetIds[0],
        );

        $this->validator->assertValid($replacement);

        return [
            'html_source' => $replacement,
            'explanation' => (string) ($response->output['explanation'] ?? 'Edited selected block.'),
            'blocks' => $this->compactBlockIndex($this->blocks->index($replacement)),
            '_llm' => [
                'provider' => $provider,
                'model' => $response->model,
                'usage' => $response->usage,
            ],
        ];
    }

    private function schema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['html_source', 'explanation'],
            'properties' => [
                'html_source' => ['type' => 'string', 'minLength' => 1],
                'explanation' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 300],
            ],
        ];
    }

    private function streamHtmlSource(Page $page, string $stage): callable
    {
        $streamed = '';

        $rawOutput = '';

        return function (string $partialJson, string $delta = '') use ($page, $stage, &$streamed, &$rawOutput): void {
            $outputChunk = $delta !== '' ? $delta : substr($partialJson, strlen($rawOutput));
            if ($outputChunk !== '') {
                $outputPosition = strlen($rawOutput);
                $rawOutput .= $outputChunk;

                $this->streamBuffer->appendOutput($page->id, $stage, $outputChunk, $outputPosition);
                broadcast(new GenerationStreamChunk($page->id, $stage, $outputChunk, $outputPosition, 'output'));
            }

            $html = $this->partialJsonStringValue($partialJson, 'html_source');

            if (! is_string($html) || strlen($html) <= strlen($streamed)) {
                return;
            }

            $position = strlen($streamed);
            $chunk = substr($html, $position);
            $streamed = $html;

            Log::debug('Broadcasting targeted edit stream chunk.', [
                'page_id' => $page->id,
                'stage' => $stage,
                'position' => $position,
                'bytes' => strlen($chunk),
            ]);

            $this->streamBuffer->append($page->id, $stage, $chunk, $position);

            broadcast(new GenerationStreamChunk($page->id, $stage, $chunk, $position));
        };
    }

    private function partialJsonStringValue(string $json, string $field): ?string
    {
        if (! preg_match('/"'.preg_quote($field, '/').'"\s*:\s*"/', $json, $matches, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $offset = $matches[0][1] + strlen($matches[0][0]);
        $value = '';
        $length = strlen($json);

        for ($i = $offset; $i < $length; $i++) {
            $char = $json[$i];

            if ($char === '"') {
                break;
            }

            if ($char !== '\\') {
                $value .= $char;

                continue;
            }

            if ($i + 1 >= $length) {
                break;
            }

            $next = $json[++$i];
            $value .= match ($next) {
                '"', '\\', '/' => $next,
                'b' => "\b",
                'f' => "\f",
                'n' => "\n",
                'r' => "\r",
                't' => "\t",
                'u' => $this->decodeUnicodeEscape(substr($json, $i + 1, 4), $i),
                default => $next,
            };
        }

        return $value;
    }

    private function decodeUnicodeEscape(string $hex, int &$index): string
    {
        if (! preg_match('/^[0-9a-fA-F]{4}$/', $hex)) {
            return '';
        }

        $index += 4;

        return json_decode('"\\u'.$hex.'"', true, 512, JSON_THROW_ON_ERROR);
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
                    'data-node-id="'.$oldId.'"',
                    "data-node-id='{$oldId}'",
                    'data-tw-block="'.$oldId.'"',
                    "data-tw-block='{$oldId}'",
                ],
                [
                    'id="'.$newId.'"',
                    "id='{$newId}'",
                    'data-node-id="'.$newId.'"',
                    "data-node-id='{$newId}'",
                    'data-tw-block="'.$newId.'"',
                    "data-tw-block='{$newId}'",
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
