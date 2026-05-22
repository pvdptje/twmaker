<?php

namespace App\Services\Html;

class BlockIndexer
{
    private const BLOCK_PATTERN = '/<!--\s*tw:block\s+([^>]*)-->(.*?)<!--\s*\/tw:block\s*-->/is';

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

    public function replaceBlock(string $html, string $blockId, string $replacement): string
    {
        foreach ($this->index($html) as $block) {
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

        $wanted = array_flip($blockIds);
        $selected = [];
        foreach ($this->index($html) as $position => $block) {
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
