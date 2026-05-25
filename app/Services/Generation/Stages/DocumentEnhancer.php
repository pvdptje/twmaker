<?php

namespace App\Services\Generation\Stages;

use App\Events\GenerationStreamChunk;
use App\Models\Page;
use App\Services\Generation\GenerationStreamBuffer;
use App\Services\Html\BlockIndexer;
use App\Services\Html\HtmlDocumentValidator;
use App\Services\Html\HtmlFragmentRepairer;
use App\Services\Html\HtmlValidationException;
use App\Services\Ids\IdGenerator;
use App\Services\Llm\LlmProvider;
use App\Services\Llm\TextRequest;
use Illuminate\Support\Facades\Log;
use Throwable;

class DocumentEnhancer
{
    public function __construct(
        private readonly LlmProvider $provider,
        private readonly PromptBuilder $prompts,
        private readonly BlockIndexer $blocks,
        private readonly HtmlDocumentValidator $validator,
        private readonly HtmlFragmentRepairer $repairer,
        private readonly IdGenerator $ids,
        private readonly GenerationStreamBuffer $streamBuffer,
    ) {}

    public function enhance(Page $page, string $instruction, string $summary, ?string $provider = null, ?string $model = null, ?string $apiKey = null): array
    {
        $provider ??= (string) config('llm.default_provider', 'anthropic');
        $htmlSource = (string) ($page->html_source ?? '');
        $existingIds = array_column($this->blocks->index($htmlSource), 'id');
        $stage = 'document_enhancer';

        if (trim($htmlSource) === '') {
            throw new HtmlValidationException(['Generate a page before running enhancements.']);
        }

        if (trim($instruction) === '') {
            throw new HtmlValidationException(['Enter an enhancement prompt.']);
        }

        $this->streamBuffer->resetRun($page->id, $stage);

        $request = new TextRequest(
            stage: $stage,
            provider: $provider,
            model: $model ?: (string) config("llm.providers.{$provider}.models.targeted_edit"),
            systemPrompt: $this->prompts->system($stage),
            userPrompt: $this->buildUserPrompt($htmlSource, $instruction),
            context: [
                'page_id' => $page->id,
                'page_name' => $page->name,
                'enhancement_summary' => $summary,
                'existing_block_count' => count($existingIds),
            ],
            maxTokens: (int) config("llm.providers.{$provider}.section_max_tokens", 32000),
            temperature: 0.2,
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

        $html = $this->normalizeBlockIds(
            $this->repairer->repair($this->stripCodeFence(trim((string) $response->text))),
            $existingIds,
        );

        $this->validator->assertValid($html);

        return [
            'html_source' => $html,
            'explanation' => $summary,
            'blocks' => $this->compactBlockIndex($this->blocks->index($html)),
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

            Log::debug('Broadcasting document enhancer stream chunk.', [
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

    private function buildUserPrompt(string $htmlSource, string $instruction): string
    {
        return "Enhancement request:\n{$instruction}\n\nCurrent complete HTML document:\n{$htmlSource}\n\nReturn the complete updated HTML document.";
    }

    /**
     * @param  array<int, string>  $existingIds
     */
    private function normalizeBlockIds(string $html, array $existingIds): string
    {
        $seen = [];
        $existing = array_flip($existingIds);

        foreach (array_reverse($this->blocks->index($html)) as $block) {
            $oldId = (string) ($block['id'] ?? '');
            $keepExisting = $oldId !== '' && isset($existing[$oldId]) && ! isset($seen[$oldId]);
            $newId = $keepExisting ? $oldId : $this->ids->section();
            $seen[$newId] = true;

            if ($oldId === $newId) {
                continue;
            }

            $blockHtml = substr($html, (int) $block['start_offset'], (int) $block['end_offset'] - (int) $block['start_offset']);
            $blockHtml = $oldId === ''
                ? preg_replace('/<!--\s*tw:block\b/i', '<!-- tw:block id="'.$newId.'"', $blockHtml, 1)
                : str_replace(
                    ['id="'.$oldId.'"', "id='{$oldId}'"],
                    ['id="'.$newId.'"', "id='{$newId}'"],
                    $blockHtml,
                );

            $html = substr($html, 0, (int) $block['start_offset'])
                .$blockHtml
                .substr($html, (int) $block['end_offset']);
        }

        return $html;
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

    private function stripCodeFence(string $text): string
    {
        if (preg_match('/^```(?:html)?\s*(.*?)\s*```$/is', $text, $matches)) {
            return trim($matches[1]);
        }

        $text = preg_replace('/^\s*```[A-Za-z0-9_-]*[ \t]*(?:\R|$)/', '', $text, 1) ?? $text;
        $text = preg_replace('/(?:\R|^)```[ \t]*$/', '', $text, 1) ?? $text;

        return trim($text);
    }
}
