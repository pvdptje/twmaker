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

class SectionInserter
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

    /**
     * @param  array<int, array{base64: string, mime_type: string}>  $images
     */
    public function insert(Page $page, ?string $anchorBlockId, string $position, string $instruction, ?string $provider = null, ?string $model = null, ?string $apiKey = null, array $images = []): array
    {
        $provider ??= (string) config('llm.default_provider', 'anthropic');
        $position = $position === 'before' ? 'before' : 'after';
        $htmlSource = (string) ($page->html_source ?? '');
        $blockIndex = $this->blocks->index($htmlSource);
        $anchorBlock = $this->resolveAnchor($blockIndex, $anchorBlockId);
        $existingIds = array_column($blockIndex, 'id');

        $stage = 'section_inserter';
        $this->streamBuffer->resetRun($page->id, $stage);

        $request = new TextRequest(
            stage: $stage,
            provider: $provider,
            model: $model ?: (string) config("llm.providers.{$provider}.models.targeted_edit"),
            systemPrompt: $this->prompts->system('section_inserter'),
            userPrompt: $this->buildUserPrompt($instruction, $position, $anchorBlock, $existingIds, $htmlSource, $images),
            context: [
                'page_id' => $page->id,
                'page_name' => $page->name,
                'anchor_id' => $anchorBlock['id'] ?? '',
                'position' => $position,
                'reference_images' => count($images),
            ],
            maxTokens: (int) config("llm.providers.{$provider}.edit_max_tokens", 8000),
            temperature: 0.6,
            apiKey: $apiKey,
            images: $images,
        );

        try {
            if (! method_exists($this->provider, 'sendTextStream')) {
                throw new \RuntimeException('The configured LLM provider does not support plain text streaming.');
            }

            $response = $this->provider->sendTextStream($request, $this->streamHtml($page, $stage));
        } finally {
            $this->streamBuffer->flushRun($page->id, $stage);
        }

        $newBlock = $this->normalizeNewBlock(
            $this->repairer->repair($this->stripCodeFence(trim((string) $response->text))),
            $existingIds,
        );

        $this->validator->assertValid($newBlock);

        return [
            'html_source' => $newBlock,
            'explanation' => 'Inserted new section '.$position.' '.($anchorBlock['id'] ?? 'page').'.',
            'blocks' => $this->compactBlockIndex($this->blocks->index($newBlock)),
            'anchor_id' => $anchorBlock['id'] ?? null,
            'position' => $position,
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

            Log::debug('Broadcasting section inserter stream chunk.', [
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
     * @param  array<int, array{id: string, type: string, label: string, start_offset: int, end_offset: int, html: string, summary: string|null}>  $blockIndex
     * @return array{id: string, type: string, label: string, start_offset: int, end_offset: int, html: string, summary: string|null}|array{id: null}
     */
    private function resolveAnchor(array $blockIndex, ?string $anchorBlockId): array
    {
        if ($anchorBlockId === null || $anchorBlockId === '') {
            return ['id' => null];
        }

        foreach ($blockIndex as $block) {
            if ($block['id'] === $anchorBlockId) {
                return $block;
            }
        }

        throw new HtmlValidationException(["Anchor block [{$anchorBlockId}] was not found."]);
    }

    /**
     * @param  array<int, string>  $existingIds
     * @param  array<int, array{base64: string, mime_type: string}>  $images
     */
    private function buildUserPrompt(string $instruction, string $position, array $anchorBlock, array $existingIds, string $htmlSource, array $images = []): string
    {
        $blockList = '';
        foreach ($existingIds as $id) {
            $blockList .= "- {$id}\n";
        }
        if ($blockList === '') {
            $blockList = '(page is empty)';
        }

        $anchorId = (string) ($anchorBlock['id'] ?? '');
        $anchorDescription = $anchorId !== ''
            ? "Anchor block id: {$anchorId} (type: ".(string) ($anchorBlock['type'] ?? 'block').', label: "'.(string) ($anchorBlock['label'] ?? '').'")'
            : 'No anchor block (insert at the '.($position === 'before' ? 'top' : 'bottom').' of the page).';

        $positionNote = $anchorId !== ''
            ? 'Insert position: '.strtoupper($position).' the anchor block.'
            : 'Insert position: at the '.($position === 'before' ? 'top' : 'bottom').' of the page.';

        $context = $this->surroundingHtml($htmlSource, $anchorBlock);
        $contextSection = $context !== '' ? "Surrounding HTML for visual style context:\n{$context}" : 'Page is currently empty.';

        $imageNote = $images !== []
            ? 'A visual reference '.(count($images) === 1 ? 'screenshot is' : count($images).' screenshots are')
                .' attached. Recreate the layout, hierarchy, colors and overall style shown in the reference while honoring the user instruction. Adapt the content to fit the rest of the page when the reference conflicts with the surrounding context.'
            : null;

        return implode("\n\n", array_filter([
            "User instruction for the new section:\n{$instruction}",
            $imageNote,
            $anchorDescription,
            $positionNote,
            "Existing block ids on the page (do not reuse):\n".trim($blockList),
            $contextSection,
            'Return exactly one new tw:block region.',
        ]));
    }

    private function surroundingHtml(string $htmlSource, array $anchorBlock): string
    {
        if (! isset($anchorBlock['start_offset'])) {
            return mb_strlen($htmlSource) > 5000 ? substr($htmlSource, 0, 5000) : $htmlSource;
        }

        $start = max(0, (int) $anchorBlock['start_offset'] - 2500);
        $end = min(strlen($htmlSource), (int) $anchorBlock['end_offset'] + 2500);

        return substr($htmlSource, $start, $end - $start);
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

    /**
     * @param  array<int, string>  $existingIds
     */
    private function normalizeNewBlock(string $html, array $existingIds): string
    {
        $blocks = $this->blocks->index($html);

        if ($blocks === []) {
            throw new HtmlValidationException(['Inserter must return one tw:block region.']);
        }

        if (count($blocks) > 1) {
            $first = $blocks[0];
            $html = substr($html, (int) $first['start_offset'], (int) $first['end_offset'] - (int) $first['start_offset']);
            $blocks = $this->blocks->index($html);
        }

        $existing = array_flip($existingIds);

        foreach (array_reverse($blocks) as $block) {
            $oldId = (string) ($block['id'] ?? '');
            if ($oldId === '' || isset($existing[$oldId])) {
                $newId = $this->ids->section();
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
        }

        return $html;
    }
}
