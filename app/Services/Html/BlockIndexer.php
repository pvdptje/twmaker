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
        $text = trim(preg_replace('/\s+/', ' ', strip_tags($html)) ?? '');

        if ($text === '') {
            return null;
        }

        return strlen($text) > 160 ? substr($text, 0, 157).'...' : $text;
    }
}
