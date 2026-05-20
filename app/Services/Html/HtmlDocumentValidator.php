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

        if (preg_match('/<\s*script\b/i', $html)) {
            $errors[] = 'HTML source must not contain script tags.';
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

        $blocks = $this->blocks->index($html);
        if ($blocks === []) {
            $errors[] = 'HTML source must contain at least one tw:block marker.';
        }

        $ids = [];
        foreach ($blocks as $block) {
            if ($block['id'] === '') {
                $errors[] = 'Every block marker must include an id attribute.';
            }

            if (isset($ids[$block['id']])) {
                $errors[] = "Duplicate block id [{$block['id']}].";
            }
            $ids[$block['id']] = true;

            if (! preg_match('/data-node-id=["\']'.preg_quote($block['id'], '/').'["\']/', $block['html'])) {
                $errors[] = "Block [{$block['id']}] must contain a matching data-node-id attribute.";
            }

            if (! preg_match('/data-tw-block=["\']'.preg_quote($block['id'], '/').'["\']/', $block['html'])) {
                $errors[] = "Block [{$block['id']}] must contain a matching data-tw-block attribute.";
            }
        }

        return array_values(array_unique($errors));
    }

    public function assertValid(string $html): void
    {
        $errors = $this->errors($html);

        if ($errors !== []) {
            throw new HtmlValidationException($errors);
        }
    }
}
