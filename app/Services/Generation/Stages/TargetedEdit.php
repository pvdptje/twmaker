<?php

namespace App\Services\Generation\Stages;

use App\Models\Page;
use App\Services\Html\BlockIndexer;
use App\Services\Html\HtmlDocumentValidator;
use App\Services\Html\HtmlValidationException;
use App\Services\Ids\IdGenerator;
use App\Services\Llm\LlmProvider;
use App\Services\Llm\StructuredRequest;

class TargetedEdit
{
    public function __construct(
        private readonly LlmProvider $provider,
        private readonly PromptBuilder $prompts,
        private readonly BlockIndexer $blocks,
        private readonly HtmlDocumentValidator $validator,
        private readonly IdGenerator $ids,
    ) {}

    public function edit(Page $page, string $targetId, string $instruction, ?string $provider = null, ?string $model = null, ?string $apiKey = null): array
    {
        $provider ??= (string) config('llm.default_provider', 'anthropic');
        $htmlSource = (string) ($page->html_source ?? '');
        $blockIndex = $this->blocks->index($htmlSource);
        $targetBlock = collect($blockIndex)->firstWhere('id', $targetId);

        if (! is_array($targetBlock)) {
            throw new HtmlValidationException(["Block [{$targetId}] was not found."]);
        }

        $response = $this->provider->sendStructured(new StructuredRequest(
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
                'target_id' => $targetId,
                'target_block' => $targetBlock,
                'block_index' => $this->compactBlockIndex($blockIndex),
                'surrounding_html' => $this->surroundingHtml($htmlSource, $targetBlock),
                'instructions' => [
                    'Return one or more complete tw:block regions as html_source.',
                    'The first returned block replaces the selected block identity; extra returned blocks become new sections.',
                    'Do not include script tags, inline event handlers, or javascript: URLs.',
                ],
            ],
            maxTokens: (int) config("llm.providers.{$provider}.edit_max_tokens", 8000),
            temperature: 0.4,
            apiKey: $apiKey,
        ));

        $replacement = $this->normalizeReplacementIds(
            (string) ($response->output['html_source'] ?? ''),
            $targetId,
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

    private function surroundingHtml(string $htmlSource, array $targetBlock): string
    {
        $start = max(0, (int) $targetBlock['start_offset'] - 2500);
        $end = min(strlen($htmlSource), (int) $targetBlock['end_offset'] + 2500);

        return substr($htmlSource, $start, $end - $start);
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
