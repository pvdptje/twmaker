<?php

namespace App\Services\Html;

class DeterministicBlockMarker
{
    private const VOID_TAGS = [
        'area' => true,
        'base' => true,
        'br' => true,
        'col' => true,
        'embed' => true,
        'hr' => true,
        'img' => true,
        'input' => true,
        'link' => true,
        'meta' => true,
        'param' => true,
        'source' => true,
        'track' => true,
        'wbr' => true,
    ];

    /**
     * @param  array<int, array<string, mixed>>  $sections
     */
    public function mark(string $html, array $sections = []): string
    {
        if (trim($html) === '' || preg_match('/<!--\s*tw:block\b/i', $html)) {
            return $html;
        }

        $elements = $this->topLevelElements($html, 0, strlen($html));
        $targets = $this->blockTargets($html, $elements);

        if ($targets === []) {
            return '';
        }

        return $this->wrapTargets($html, $targets, $sections);
    }

    /**
     * @param  array<int, array<string, int|string>>  $elements
     * @return array<int, array<string, int|string>>
     */
    private function blockTargets(string $html, array $elements): array
    {
        if (count($elements) === 1 && $elements[0]['tag'] === 'main') {
            $children = $this->topLevelElements(
                $html,
                (int) $elements[0]['open_end'],
                (int) $elements[0]['close_start'],
            );

            if ($children !== []) {
                return $children;
            }
        }

        return $elements;
    }

    /**
     * @return array<int, array{tag: string, start: int, open_end: int, close_start: int, end: int}>
     */
    private function topLevelElements(string $html, int $start, int $end): array
    {
        $elements = [];
        $stack = [];
        $current = null;
        $offset = $start;

        while (preg_match('/<!--.*?-->|<\s*(\/)?\s*([a-zA-Z][a-zA-Z0-9:-]*)\b[^>]*>/s', $html, $match, PREG_OFFSET_CAPTURE, $offset)) {
            $token = $match[0][0];
            $tokenStart = $match[0][1];
            $tokenEnd = $tokenStart + strlen($token);

            if ($tokenStart >= $end) {
                break;
            }

            $offset = min($tokenEnd, $end);

            if (str_starts_with($token, '<!--')) {
                continue;
            }

            $closing = ($match[1][0] ?? '') === '/';
            $tag = strtolower($match[2][0] ?? '');

            if ($tag === '' || isset(self::VOID_TAGS[$tag])) {
                if ($current === null && ! $closing) {
                    $elements[] = [
                        'tag' => $tag,
                        'start' => $tokenStart,
                        'open_end' => $tokenEnd - 1,
                        'close_start' => $tokenStart,
                        'end' => $tokenEnd,
                    ];
                }

                continue;
            }

            if (! $closing) {
                if ($stack === []) {
                    $current = [
                        'tag' => $tag,
                        'start' => $tokenStart,
                        'open_end' => $tokenEnd - 1,
                        'close_start' => $end,
                        'end' => $end,
                    ];
                }

                $stack[] = $tag;

                continue;
            }

            $index = $this->lastStackIndex($stack, $tag);
            if ($index === null) {
                continue;
            }

            $stack = array_slice($stack, 0, $index);
            if ($stack === [] && $current !== null) {
                $current['close_start'] = $tokenStart;
                $current['end'] = $tokenEnd;
                $elements[] = $current;
                $current = null;
            }
        }

        if ($current !== null) {
            $elements[] = $current;
        }

        return $elements;
    }

    /**
     * @param  array<int, string>  $stack
     */
    private function lastStackIndex(array $stack, string $tag): ?int
    {
        for ($index = count($stack) - 1; $index >= 0; $index--) {
            if ($stack[$index] === $tag) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @param  array<int, array<string, int|string>>  $targets
     * @param  array<int, array<string, mixed>>  $sections
     */
    private function wrapTargets(string $html, array $targets, array $sections): string
    {
        for ($index = count($targets) - 1; $index >= 0; $index--) {
            $target = $targets[$index];
            $meta = $this->blockMeta((string) $target['tag'], $sections, $index);
            $openComment = sprintf(
                '<!-- tw:block id="%s" type="%s" label="%s" -->'."\n",
                $meta['id'],
                $meta['type'],
                $meta['label'],
            );
            $closeComment = "\n".'<!-- /tw:block -->';

            $html = substr($html, 0, (int) $target['end'])
                .$closeComment
                .substr($html, (int) $target['end']);
            $html = substr($html, 0, (int) $target['start'])
                .$openComment
                .substr($html, (int) $target['start']);
        }

        return trim($html);
    }

    /**
     * @param  array<int, array<string, mixed>>  $sections
     * @return array{id: string, type: string, label: string}
     */
    private function blockMeta(string $tag, array $sections, int $index): array
    {
        $type = $this->sectionType($sections[$index]['type'] ?? null, $tag, $index);

        return [
            'id' => 'block_'.$this->uniqueSuffix($type, $index),
            'type' => $type,
            'label' => $this->label($type),
        ];
    }

    private function sectionType(mixed $plannedType, string $tag, int $index): string
    {
        if (is_string($plannedType) && trim($plannedType) !== '') {
            return $this->slug($plannedType) ?: 'section';
        }

        $candidate = $tag === 'section' && $index === 0 ? 'hero' : $tag;

        return $this->slug($candidate) ?: 'section';
    }

    private function uniqueSuffix(string $type, int $index): string
    {
        return $index === 0 ? $type : $type.'_'.($index + 1);
    }

    private function label(string $type): string
    {
        return str((string) preg_replace('/[_-]+/', ' ', $type))->title()->toString();
    }

    private function slug(string $value): string
    {
        $slug = strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '_', $value));

        return trim($slug, '_');
    }
}
