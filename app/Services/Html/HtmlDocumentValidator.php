<?php

namespace App\Services\Html;

class HtmlDocumentValidator
{
    public function __construct(private readonly BlockIndexer $blocks) {}

    /**
     * @return array<int, string>
     */
    public function errors(string $html): array
    {
        $errors = [];

        if (trim($html) === '') {
            $errors[] = 'HTML source is empty.';
        }

        foreach ($this->scriptTags($html) as $scriptTag) {
            if (! $this->isAllowedScriptTag($scriptTag)) {
                $errors[] = 'HTML source may only contain external https script tags with no inline body.';
                break;
            }
        }

        if (preg_match('/\son[a-z]+\s*=/i', $html)) {
            $errors[] = 'HTML source must not contain inline event handler attributes.';
        }

        if (preg_match('/javascript\s*:/i', $html)) {
            $errors[] = 'HTML source must not contain javascript: URLs.';
        }

        $openCount = preg_match_all('/<!--\s*tw:block\b/i', $html);
        $closeCount = preg_match_all('/<!--\s*\/tw:block\s*-->/i', $html);
        if ($openCount !== $closeCount) {
            $errors[] = 'Block markers are unbalanced.';
        }

        $groupOpenCount = preg_match_all('/<!--\s*tw:group\b/i', $html);
        $groupCloseCount = preg_match_all('/<!--\s*\/tw:group\s*-->/i', $html);
        if ($groupOpenCount !== $groupCloseCount) {
            $errors[] = 'Group markers are unbalanced.';
        }

        if ($this->hasNestedBlockMarkers($html)) {
            $errors[] = 'Block markers must not be nested.';
        }

        $blocks = $this->blocks->index($html);
        if ($blocks === []) {
            $errors[] = 'HTML source must contain at least one tw:block marker.';
        }

        $ids = [];
        foreach ($this->blocks->indexSelectable($html) as $block) {
            $kind = (string) ($block['kind'] ?? 'block');
            if ($block['id'] === '') {
                $errors[] = $kind === 'group'
                    ? 'Every group marker must include an id attribute.'
                    : 'Every block marker must include an id attribute.';
            }

            if (isset($ids[$block['id']])) {
                $errors[] = "Duplicate selectable id [{$block['id']}].";
            }
            $ids[$block['id']] = true;

        }

        return array_values(array_unique($errors));
    }

    private function hasNestedBlockMarkers(string $html): bool
    {
        $open = false;
        $offset = 0;

        while (preg_match('/<!--\s*(\/)?tw:block\b[^>]*-->/i', $html, $match, PREG_OFFSET_CAPTURE, $offset)) {
            $isClose = ($match[1][0] ?? '') === '/';
            $offset = $match[0][1] + strlen($match[0][0]);

            if (! $isClose) {
                if ($open) {
                    return true;
                }

                $open = true;

                continue;
            }

            $open = false;
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    private function scriptTags(string $html): array
    {
        preg_match_all('/<\s*script\b[^>]*>(.*?)<\s*\/\s*script\s*>|<\s*script\b[^>]*\/?>/is', $html, $matches);

        return $matches[0] ?? [];
    }

    private function isAllowedScriptTag(string $scriptTag): bool
    {
        if (! preg_match('/\ssrc\s*=\s*["\']([^"\']+)["\']/i', $scriptTag, $match)) {
            return false;
        }

        $src = trim($match[1]);
        if (! preg_match('#^https://[^\s"\'<>]+$#i', $src)) {
            return false;
        }

        $inner = preg_replace('/^<\s*script\b[^>]*>|<\s*\/\s*script\s*>$/is', '', $scriptTag) ?? '';

        return trim($inner) === '';
    }

    public function assertValid(string $html): void
    {
        $errors = $this->errors($html);

        if ($errors !== []) {
            throw new HtmlValidationException($errors);
        }
    }
}
