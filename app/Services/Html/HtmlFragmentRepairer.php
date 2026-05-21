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

        return $this->closeOpenTags($html);
    }

    private function stripCodeFence(string $html): string
    {
        if (preg_match('/^```(?:html)?\s*(.*?)\s*```$/is', $html, $match)) {
            return trim($match[1]);
        }

        return $html;
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
}
