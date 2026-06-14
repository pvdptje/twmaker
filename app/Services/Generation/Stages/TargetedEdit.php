<?php

namespace App\Services\Generation\Stages;

use App\Events\GenerationStreamChunk;
use App\Models\Page;
use App\Models\ProjectAsset;
use App\Services\Generation\GenerationStreamBuffer;
use App\Services\Html\BlockIndexer;
use App\Services\Html\HtmlDocumentValidator;
use App\Services\Html\HtmlFragmentRepairer;
use App\Services\Html\HtmlValidationException;
use App\Services\Ids\IdGenerator;
use App\Services\Llm\LlmProvider;
use App\Services\Llm\PromptLog;
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
        private readonly HtmlFragmentRepairer $repairer,
        private readonly IdGenerator $ids,
        private readonly GenerationStreamBuffer $streamBuffer,
    ) {}

    /**
     * @param  array<int, array{base64: string, mime_type: string}>  $images
     */
    public function edit(Page $page, string $targetId, string $instruction, ?string $provider = null, ?string $model = null, ?string $apiKey = null, array $images = [], array $ownAssets = [], ?int $referenceImageCount = null): array
    {
        return $this->editMany($page, [$targetId], $instruction, $provider, $model, $apiKey, $images, $ownAssets, $referenceImageCount);
    }

    /**
     * @param  array<int, string>  $targetIds
     * @param  array<int, array{base64: string, mime_type: string}>  $images
     */
    public function editMany(Page $page, array $targetIds, string $instruction, ?string $provider = null, ?string $model = null, ?string $apiKey = null, array $images = [], array $ownAssets = [], ?int $referenceImageCount = null): array
    {
        $provider ??= (string) config('llm.default_provider', 'anthropic');
        $referenceImageCount ??= count($images);
        $targetIds = $this->normalizeTargetIds($targetIds);
        $htmlSource = (string) ($page->html_source ?? '');
        $blockIndex = $this->targetIndex($htmlSource, $targetIds);
        $targetBlocks = $this->targetBlocks($blockIndex, $targetIds);

        $this->assertContiguous($targetBlocks);

        $stage = 'targeted_edit';
        $this->streamBuffer->resetRun($page->id, $stage);

        $request = new TextRequest(
            stage: $stage,
            provider: $provider,
            model: $model ?: (string) config("llm.providers.{$provider}.models.targeted_edit"),
            systemPrompt: $this->prompts->system('targeted_edit'),
            userPrompt: $this->buildUserPrompt($instruction, $targetIds, $targetBlocks, $htmlSource, $blockIndex, $images, $ownAssets, $referenceImageCount),
            context: [
                'page_id' => $page->id,
                'page_name' => $page->name,
                'target_ids' => implode(',', $targetIds),
                'reference_images' => $referenceImageCount,
                'own_assets' => count($ownAssets),
            ],
            maxTokens: (int) config("llm.providers.{$provider}.edit_max_tokens", 8000),
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

        $replacement = $this->normalizeReplacementIds(
            $this->repairer->repair($this->stripCodeFence(trim((string) $response->text))),
            $targetBlocks[0],
        );
        $replacement = $this->repairSelectedAssetPaths($replacement, $ownAssets, $htmlSource, $targetBlocks);

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
            '_prompt_log' => PromptLog::fromTextRequest($request),
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
     * @param  array<int, array{base64: string, mime_type: string}>  $images
     */
    private function buildUserPrompt(string $instruction, array $targetIds, array $targetBlocks, string $htmlSource, array $blockIndex, array $images = [], array $ownAssets = [], ?int $referenceImageCount = null): string
    {
        $referenceImageCount ??= count($images);
        $targetHtml = $this->targetHtml($htmlSource, $targetBlocks);
        $surrounding = $this->surroundingHtml($htmlSource, $targetBlocks);
        $compact = $this->compactBlockIndex($blockIndex);
        $blockList = '';

        foreach ($compact as $block) {
            $blockList .= "- {$block['id']} ({$block['type']}, \"{$block['label']}\")\n";
        }

        if (count($targetIds) === 1 && (string) ($targetBlocks[0]['kind'] ?? 'block') === 'group') {
            $rangeNote = 'The selected target is a tw:group wrapper. Prefer returning one complete tw:group region with the same group id when the parent container should remain selectable. The group may contain child tw:block regions, but tw:block markers must never be nested.';
        } else {
            $rangeNote = count($targetIds) === 1
                ? 'Return one or more complete tw:block regions as the replacement.'
                : 'Return one or more complete tw:block regions that replace the selected contiguous block range. The first returned block keeps the first selected block identity; extra returned blocks become new sections.';
        }

        $imageNote = $referenceImageCount > 0
            ? 'A visual reference '.($referenceImageCount === 1 ? 'screenshot is' : $referenceImageCount.' screenshots are')
                .' attached. Use the screenshot to guide the requested edit, especially layout, hierarchy, spacing, color, and visual style, while keeping the result compatible with the surrounding page.'
            : null;
        $ownAssetNote = $this->assetInsertionPrompt($ownAssets, $referenceImageCount);
        $existingAssetNote = $this->existingAssetPrompt($targetHtml, $surrounding, $ownAssets);

        return implode("\n\n", array_filter([
            "User instruction:\n{$instruction}",
            $imageNote,
            $ownAssetNote,
            $existingAssetNote,
            'Selected block ids: '.implode(', ', $targetIds),
            "All block ids in the page:\n".trim($blockList),
            "Selected block HTML (including markers):\n{$targetHtml}",
            "Surrounding HTML for context:\n{$surrounding}",
            $rangeNote,
        ]));
    }

    /**
     * @param  array<int, array{id: string, original_name: string, mime_type: string, public_url: string, width: int|null, height: int|null}>  $ownAssets
     */
    private function assetInsertionPrompt(array $ownAssets, int $referenceImageCount): ?string
    {
        if ($ownAssets === []) {
            return null;
        }

        $lines = [
            'Asset insertion task:',
            'Use the selected user-owned asset path(s) in the selected HTML block or selected contiguous block range.',
            'If the user instruction does not clearly say where to place the asset, make your best guess based on the block structure, existing images/backgrounds, asset filename, and surrounding content.',
            'Keep unrelated HTML, text, links, classes, structure, and existing asset paths intact. Change only what is needed to use the selected asset well.',
            'Return only the replacement marked block or marked contiguous block range, exactly as required by the system prompt.',
            'Each Public HTML path below is immutable. Copy it exactly, including the leading slash, every letter, every digit, every zero, and the filename extension. Treat it as a code literal, not prose.',
            'Never infer, abbreviate, retype from memory, or "correct" the path. If you use the asset, paste the exact Public HTML path string from between the backticks.',
            'Use the exact path as an `<img src>`, a CSS `background-image: url(...)`, or a Tailwind arbitrary background class such as `bg-[url(\'...\')]`, depending on what fits the requested edit.',
            'Do not rehost, crop, base64-encode, convert, or invent another path for a selected asset. Do not replace it with a remote URL or placeholder when this asset satisfies the request.',
        ];

        foreach ($ownAssets as $asset) {
            $dimensions = ($asset['width'] ?? null) !== null && ($asset['height'] ?? null) !== null
                ? "{$asset['width']}x{$asset['height']}"
                : 'unknown size';

            $lines[] = implode("\n", [
                '- Asset ID: '.$asset['id'],
                '  Public HTML path: `'.$asset['public_url'].'`',
                '  Original filename: '.$asset['original_name'],
                '  MIME type: '.$asset['mime_type'],
                '  Dimensions: '.$dimensions,
                '  Example image usage: <img src="'.$asset['public_url'].'" alt="">',
                '  Example background usage: <div style="background-image: url(\''.$asset['public_url'].'\')"></div>',
            ]);
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<int, array{id: string, original_name: string, mime_type: string, public_url: string, width: int|null, height: int|null}>  $ownAssets
     */
    private function existingAssetPrompt(string $targetHtml, string $surrounding, array $ownAssets = []): ?string
    {
        $paths = $this->userOwnedAssetPaths($targetHtml."\n".$surrounding);
        $selectedPath = count($ownAssets) === 1 && is_string($ownAssets[0]['public_url'] ?? null)
            ? $ownAssets[0]['public_url']
            : null;

        if ($paths === []) {
            return $selectedPath !== null
                ? 'User-owned asset path rule: relative paths beginning with `/assets/`, `/storage/`, `./assets/`, or `../assets/` are files the user added. Preserve unrelated existing asset paths, but when applying the selected asset to an image or background, use the selected Public HTML path exactly.'
                : 'User-owned asset path rule: relative paths beginning with `/assets/`, `/storage/`, `./assets/`, or `../assets/` are files the user added. Preserve them exactly unless the user explicitly asks to remove or replace that specific asset.';
        }

        $instruction = $selectedPath !== null
            ? "Preserve unrelated existing paths. If the requested edit applies the selected asset to an existing image/background URL, replace that URL with the selected Public HTML path exactly: `{$selectedPath}`."
            : 'Preserve these paths exactly unless the user explicitly asks to remove or replace that specific asset.';

        return "Existing user-owned asset paths found in the selected/surrounding HTML:\n"
            .implode("\n", array_map(fn (string $path): string => "- {$path}", $paths))
            ."\n".$instruction;
    }

    /**
     * @return array<int, string>
     */
    private function userOwnedAssetPaths(string $html): array
    {
        preg_match_all('/(?:"|\'|\(|\s)((?:\/assets\/|\/storage\/|\.\/assets\/|\.\.\/assets\/)[^"\'\)\s<>]+)/i', $html, $matches);

        return array_slice(array_values(array_unique($matches[1] ?? [])), 0, 20);
    }

    /**
     * @param  array<int, array{id: string, original_name: string, mime_type: string, public_url: string, width: int|null, height: int|null}>  $ownAssets
     * @param  array<int, array<string, mixed>>  $targetBlocks
     */
    private function repairSelectedAssetPaths(string $html, array $ownAssets, string $htmlSource, array $targetBlocks): string
    {
        if (count($ownAssets) !== 1 || ! is_string($ownAssets[0]['public_url'] ?? null) || $ownAssets[0]['public_url'] === '') {
            return $html;
        }

        $selectedPath = $ownAssets[0]['public_url'];
        $existingPaths = array_flip($this->userOwnedAssetPaths(
            $this->targetHtml($htmlSource, $targetBlocks)."\n".$this->surroundingHtml($htmlSource, $targetBlocks)
        ));

        return preg_replace_callback(
            "/(?P<prefix>^|[\"'\\(\\s])(?P<path>\\/assets\\/[^\"'\\)\\s<>]+)/i",
            function (array $match) use ($selectedPath, $existingPaths): string {
                $path = (string) $match['path'];

                if ($path === $selectedPath) {
                    return $match[0];
                }

                if (! preg_match('/^\/assets\/[^\/]+\/asset_[^\/]+\.(png|jpe?g|webp)$/i', $path)) {
                    return $match[0];
                }

                if (isset($existingPaths[$path]) && $this->validProjectAssetPath($path)) {
                    return $match[0];
                }

                return (string) $match['prefix'].$selectedPath;
            },
            $html,
        ) ?? $html;
    }

    private function validProjectAssetPath(string $path): bool
    {
        if (! preg_match('/^\/assets\/(?P<project>[^\/]+)\/(?P<filename>asset_[^\/]+\.(?:png|jpe?g|webp))$/i', $path, $matches)) {
            return false;
        }

        $assetId = pathinfo((string) $matches['filename'], PATHINFO_FILENAME);

        return ProjectAsset::query()
            ->whereKey($assetId)
            ->where('project_id', (string) $matches['project'])
            ->where('path', 'like', '%/'.(string) $matches['filename'])
            ->exists();
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

    /**
     * @param  array<int, string>  $targetIds
     * @return array<int, array<string, mixed>>
     */
    private function targetIndex(string $htmlSource, array $targetIds): array
    {
        $blocks = $this->blocks->index($htmlSource);
        $known = array_flip(array_column($blocks, 'id'));

        foreach ($targetIds as $id) {
            if (! isset($known[$id])) {
                return $this->blocks->indexSelectable($htmlSource);
            }
        }

        return $blocks;
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
     * @param  array<int, array{id: string, type: string, label: string, start_offset: int, end_offset: int, html: string, summary: string|null, kind?: string}>  $blockIndex
     * @param  array<int, string>  $targetIds
     * @return array<int, array{id: string, type: string, label: string, start_offset: int, end_offset: int, html: string, summary: string|null, kind?: string, position: int}>
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

    /**
     * @param  array{id: string, kind?: string}  $targetBlock
     */
    private function normalizeReplacementIds(string $replacement, array $targetBlock): string
    {
        $targetId = (string) $targetBlock['id'];
        if (($targetBlock['kind'] ?? 'block') === 'group') {
            return $this->normalizeGroupReplacementId($replacement, $targetId);
        }

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

    private function normalizeGroupReplacementId(string $replacement, string $targetId): string
    {
        $groups = $this->blocks->indexGroups($replacement);
        if ($groups === []) {
            return $replacement;
        }

        $first = $groups[0];
        $oldId = (string) ($first['id'] ?? '');

        if ($oldId === '' || $oldId === $targetId) {
            return $replacement;
        }

        $groupHtml = substr($replacement, (int) $first['start_offset'], (int) $first['end_offset'] - (int) $first['start_offset']);
        $groupHtml = str_replace(
            [
                'id="'.$oldId.'"',
                "id='{$oldId}'",
            ],
            [
                'id="'.$targetId.'"',
                "id='{$targetId}'",
            ],
            $groupHtml,
        );

        return substr($replacement, 0, (int) $first['start_offset'])
            .$groupHtml
            .substr($replacement, (int) $first['end_offset']);
    }
}
