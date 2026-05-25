<?php

namespace App\Services\Html;

class BlockIndexer
{
    private const BLOCK_PATTERN = '/<!--\s*tw:block\s+([^>]*)-->(.*?)<!--\s*\/tw:block\s*-->/is';
    private const GROUP_PATTERN = '/<!--\s*tw:group\s+([^>]*)-->(.*?)<!--\s*\/tw:group\s*-->/is';

    /**
     * @return array<int, array{id: string, type: string, label: string, start_offset: int, end_offset: int, html: string, summary: string|null}>
     */
    public function index(string $html): array
    {
        preg_match_all(self::BLOCK_PATTERN, $html, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        return collect($matches)
            ->map(function (array $match): array {
                $attributes = $this->commentAttributes($match[1][0]);
                $blockHtml = $match[0][0];
                $start = $match[0][1];

                return [
                    'id' => (string) ($attributes['id'] ?? ''),
                    'type' => (string) ($attributes['type'] ?? 'custom'),
                    'label' => (string) ($attributes['label'] ?? ucfirst((string) ($attributes['type'] ?? 'Block'))),
                    'start_offset' => $start,
                    'end_offset' => $start + strlen($blockHtml),
                    'html' => $blockHtml,
                    'summary' => $this->summary($match[2][0]),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id: string, type: string, label: string, start_offset: int, end_offset: int, html: string, summary: string|null, kind: string}>
     */
    public function indexSelectable(string $html): array
    {
        $items = array_merge(
            array_map(fn (array $block): array => $block + ['kind' => 'block'], $this->index($html)),
            $this->indexGroups($html),
        );

        usort($items, fn (array $left, array $right): int => ((int) $left['start_offset']) <=> ((int) $right['start_offset']));

        return array_values($items);
    }

    /**
     * @return array<int, array{id: string, type: string, label: string, start_offset: int, end_offset: int, html: string, summary: string|null, kind: string, parent_id: string|null, depth: int, child_count: int}>
     */
    public function indexOutline(string $html): array
    {
        $items = $this->indexSelectable($html);
        $groups = array_values(array_filter($items, fn (array $item): bool => ($item['kind'] ?? 'block') === 'group'));

        foreach ($items as $index => $item) {
            $parent = null;

            foreach ($groups as $group) {
                if ($group['id'] === $item['id']) {
                    continue;
                }

                if ((int) $group['start_offset'] >= (int) $item['start_offset']
                    || (int) $group['end_offset'] <= (int) $item['end_offset']) {
                    continue;
                }

                if ($parent === null || (int) $group['start_offset'] > (int) $parent['start_offset']) {
                    $parent = $group;
                }
            }

            $items[$index]['parent_id'] = $parent['id'] ?? null;
            $items[$index]['depth'] = $parent === null ? 0 : 1;
            $items[$index]['child_count'] = 0;
        }

        foreach ($items as $item) {
            $parentId = $item['parent_id'] ?? null;
            if ($parentId === null) {
                continue;
            }

            foreach ($items as $index => $candidate) {
                if (($candidate['id'] ?? null) === $parentId) {
                    $items[$index]['child_count']++;
                    break;
                }
            }
        }

        return $items;
    }

    /**
     * @return array<int, array{id: string, type: string, label: string, start_offset: int, end_offset: int, html: string, summary: string|null, kind: string}>
     */
    public function indexGroups(string $html): array
    {
        preg_match_all(self::GROUP_PATTERN, $html, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        return collect($matches)
            ->map(function (array $match): array {
                $attributes = $this->commentAttributes($match[1][0]);
                $groupHtml = $match[0][0];
                $start = $match[0][1];

                return [
                    'id' => (string) ($attributes['id'] ?? ''),
                    'type' => (string) ($attributes['type'] ?? 'group'),
                    'label' => (string) ($attributes['label'] ?? ucfirst((string) ($attributes['type'] ?? 'Group'))),
                    'start_offset' => $start,
                    'end_offset' => $start + strlen($groupHtml),
                    'html' => $groupHtml,
                    'summary' => $this->summary($match[2][0]),
                    'kind' => 'group',
                ];
            })
            ->values()
            ->all();
    }

    public function replaceBlock(string $html, string $blockId, string $replacement): string
    {
        foreach ($this->indexSelectable($html) as $block) {
            if ($block['id'] !== $blockId) {
                continue;
            }

            return substr($html, 0, $block['start_offset'])
                .$replacement
                .substr($html, $block['end_offset']);
        }

        throw new HtmlValidationException(["Block [{$blockId}] was not found."]);
    }

    /**
     * @param  array<int, string>  $blockIds
     */
    public function replaceBlocks(string $html, array $blockIds, string $replacement): string
    {
        $blockIds = array_values(array_unique(array_filter(
            $blockIds,
            fn (mixed $id): bool => is_string($id) && $id !== '',
        )));

        if ($blockIds === []) {
            throw new HtmlValidationException(['At least one block must be selected.']);
        }

        if (count($blockIds) === 1) {
            return $this->replaceBlock($html, $blockIds[0], $replacement);
        }

        $index = $this->replacementIndex($html, $blockIds);
        $wanted = array_flip($blockIds);
        $selected = [];
        foreach ($index as $position => $block) {
            if (isset($wanted[$block['id']])) {
                $selected[$position] = $block;
            }
        }

        $missing = array_values(array_diff($blockIds, array_column($selected, 'id')));
        if ($missing !== []) {
            throw new HtmlValidationException(['Block ['.implode(', ', $missing).'] was not found.']);
        }

        $positions = array_keys($selected);
        sort($positions);
        if (($positions[count($positions) - 1] - $positions[0] + 1) !== count($positions)) {
            throw new HtmlValidationException(['Selected blocks must be contiguous.']);
        }

        $first = $selected[$positions[0]];
        $last = $selected[$positions[count($positions) - 1]];

        return substr($html, 0, $first['start_offset'])
            .$replacement
            .substr($html, $last['end_offset']);
    }

    public function removeBlock(string $html, string $blockId): string
    {
        foreach ($this->indexSelectable($html) as $block) {
            if ($block['id'] !== $blockId) {
                continue;
            }

            $before = substr($html, 0, $block['start_offset']);
            $after = substr($html, $block['end_offset']);

            return rtrim($before)."\n".ltrim($after);
        }

        throw new HtmlValidationException(["Block [{$blockId}] was not found."]);
    }

    public function moveBlock(string $html, string $sourceBlockId, string $targetBlockId, string $position): string
    {
        if ($position !== 'before' && $position !== 'after') {
            throw new HtmlValidationException(["Move position must be 'before' or 'after'."]);
        }

        $sourceBlockId = trim($sourceBlockId);
        $targetBlockId = trim($targetBlockId);

        if ($sourceBlockId === '' || $targetBlockId === '') {
            throw new HtmlValidationException(['A source and target block id are required to move a section.']);
        }

        if ($sourceBlockId === $targetBlockId) {
            return $html;
        }

        $outline = $this->indexOutline($html);
        $source = $this->findOutlineItem($outline, $sourceBlockId);
        $target = $this->findOutlineItem($outline, $targetBlockId);

        if ($source === null) {
            throw new HtmlValidationException(["Block [{$sourceBlockId}] was not found."]);
        }

        if ($target === null) {
            throw new HtmlValidationException(["Block [{$targetBlockId}] was not found."]);
        }

        if (($source['parent_id'] ?? null) !== ($target['parent_id'] ?? null)) {
            throw new HtmlValidationException(['Grouped items can only be moved within their parent group. Move the group row to reorder the whole group.']);
        }

        return $this->insertSelectable(
            $this->removeBlock($html, $sourceBlockId),
            $targetBlockId,
            $position,
            (string) $source['html'],
        );
    }

    public function insertBlocks(string $html, string $anchorBlockId, string $position, string $newBlocksHtml): string
    {
        if ($position !== 'before' && $position !== 'after') {
            throw new HtmlValidationException(["Insert position must be 'before' or 'after'."]);
        }

        if ($anchorBlockId === '') {
            return $position === 'before'
                ? $newBlocksHtml."\n".$html
                : $html."\n".$newBlocksHtml;
        }

        foreach ($this->index($html) as $block) {
            if ($block['id'] !== $anchorBlockId) {
                continue;
            }

            $offset = $position === 'before' ? $block['start_offset'] : $block['end_offset'];

            return substr($html, 0, $offset)
                ."\n".$newBlocksHtml."\n"
                .substr($html, $offset);
        }

        throw new HtmlValidationException(["Block [{$anchorBlockId}] was not found."]);
    }

    public function insertSelectable(string $html, string $anchorBlockId, string $position, string $newHtml): string
    {
        if ($position !== 'before' && $position !== 'after') {
            throw new HtmlValidationException(["Insert position must be 'before' or 'after'."]);
        }

        if ($anchorBlockId === '') {
            return $position === 'before'
                ? $newHtml."\n".$html
                : $html."\n".$newHtml;
        }

        foreach ($this->indexOutline($html) as $item) {
            if ($item['id'] !== $anchorBlockId) {
                continue;
            }

            $offset = $position === 'before' ? $item['start_offset'] : $item['end_offset'];

            return substr($html, 0, (int) $offset)
                ."\n".$newHtml."\n"
                .substr($html, (int) $offset);
        }

        throw new HtmlValidationException(["Block [{$anchorBlockId}] was not found."]);
    }

    /**
     * @param  array<int, string>  $blockIds
     * @return array<int, array{id: string, type: string, label: string, start_offset: int, end_offset: int, html: string, summary: string|null, kind?: string}>
     */
    private function replacementIndex(string $html, array $blockIds): array
    {
        $blocks = $this->index($html);
        $known = array_flip(array_column($blocks, 'id'));

        foreach ($blockIds as $id) {
            if (! isset($known[$id])) {
                return $this->indexSelectable($html);
            }
        }

        return $blocks;
    }

    /**
     * @param  array<int, array<string, mixed>>  $outline
     * @return array<string, mixed>|null
     */
    private function findOutlineItem(array $outline, string $id): ?array
    {
        foreach ($outline as $item) {
            if (($item['id'] ?? null) === $id) {
                return $item;
            }
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    public function commentAttributes(string $text): array
    {
        preg_match_all('/([a-zA-Z_:-]+)="([^"]*)"/', $text, $matches, PREG_SET_ORDER);

        $attributes = [];
        foreach ($matches as $match) {
            $attributes[$match[1]] = $match[2];
        }

        return $attributes;
    }

    private function summary(string $html): ?string
    {
        $text = $this->scrubText(trim(preg_replace('/\s+/', ' ', strip_tags($html)) ?? ''));

        if ($text === '') {
            return null;
        }

        return mb_strlen($text, 'UTF-8') > 160 ? mb_substr($text, 0, 157, 'UTF-8').'...' : $text;
    }

    private function scrubText(string $value): string
    {
        if (mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        return mb_scrub($value, 'UTF-8');
    }
}
