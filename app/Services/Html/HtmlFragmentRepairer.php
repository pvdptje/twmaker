<?php

namespace App\Services\Html;

class HtmlFragmentRepairer
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

    public function repair(string $html): string
    {
        $html = $this->stripCodeFence(trim($html));
        $html = $this->stripBareLanguageLabel($html);

        return $this->flattenNestedBlockMarkers($this->closeOpenTags($html));
    }

    private function stripCodeFence(string $html): string
    {
        if (preg_match('/^```(?:html)?\s*(.*?)\s*```$/is', $html, $match)) {
            return trim($match[1]);
        }

        return $html;
    }

    private function stripBareLanguageLabel(string $html): string
    {
        return preg_replace('/^\s*html\s*(?:\R|$)/i', '', $html, 1) ?? $html;
    }

    private function closeOpenTags(string $html): string
    {
        $stack = [];

        preg_match_all('/<!--.*?-->|<\s*(\/)?\s*([a-zA-Z][a-zA-Z0-9:-]*)\b[^>]*>/s', $html, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $token = $match[0];
            if (str_starts_with($token, '<!--')) {
                continue;
            }

            $closing = ($match[1] ?? '') === '/';
            $tag = strtolower($match[2] ?? '');

            if ($tag === '' || isset(self::VOID_TAGS[$tag]) || str_ends_with(rtrim($token), '/>')) {
                continue;
            }

            if (! $closing) {
                $stack[] = $tag;

                continue;
            }

            for ($index = count($stack) - 1; $index >= 0; $index--) {
                if ($stack[$index] !== $tag) {
                    continue;
                }

                $stack = array_slice($stack, 0, $index);
                break;
            }
        }

        while ($tag = array_pop($stack)) {
            $html .= "</{$tag}>";
        }

        return $html;
    }

    private function flattenNestedBlockMarkers(string $html): string
    {
        preg_match_all('/<!--\s*(\/)?tw:block\b[^>]*-->/i', $html, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        if ($matches === []) {
            return $html;
        }

        $stack = [];
        $removeRanges = [];

        foreach ($matches as $match) {
            $marker = $match[0][0];
            $start = $match[0][1];
            $end = $start + strlen($marker);
            $isClose = ($match[1][0] ?? '') === '/';

            if (! $isClose) {
                if ($stack !== []) {
                    $stack[count($stack) - 1]['has_child'] = true;
                }

                $stack[] = [
                    'open_start' => $start,
                    'open_end' => $end,
                    'has_child' => false,
                ];

                continue;
            }

            if ($stack === []) {
                continue;
            }

            $frame = array_pop($stack);

            if (! $frame['has_child']) {
                continue;
            }

            $removeRanges[] = [$frame['open_start'], $frame['open_end']];
            $removeRanges[] = [$start, $end];
        }

        if ($removeRanges === []) {
            return $html;
        }

        usort($removeRanges, fn (array $left, array $right): int => $right[0] <=> $left[0]);

        foreach ($removeRanges as [$start, $end]) {
            $html = substr($html, 0, $start).substr($html, $end);
        }

        return $html;
    }
}
