<?php

namespace App\Services\Generation;

use App\Models\Page;
use App\Services\Html\BlockIndexer;

class RelatedPagePromptBuilder
{
    public function __construct(private readonly BlockIndexer $blocks) {}

    public function build(Page $sourcePage, Page $targetPage, string $brief): string
    {
        $brief = trim($brief);
        $sourceHtml = (string) ($sourcePage->html_source ?? '');
        $sourceExcerpt = $this->sourceExcerpt($sourceHtml);
        $header = $this->regionHtml($sourceHtml, 'header');
        $footer = $this->regionHtml($sourceHtml, 'footer');

        return trim(implode("\n\n", array_filter([
            "Create a new page for the same website as the source page.\nNew page name: {$targetPage->name}",
            $brief !== '' ? "User brief for the new page:\n{$brief}" : 'User brief for the new page: Choose the most useful next page for this website based on the source page.',
            "Source page name: {$sourcePage->name}",
            'Match the source page visual system: typography, spacing rhythm, color palette, border radius, button style, navigation style, section density, and overall tone.',
            $header !== '' ? "Exact header to reuse:\n{$header}\n\nPaste this header into the new document unchanged, including block comments, ids, classes, content, links, and formatting." : null,
            $footer !== '' ? "Exact footer to reuse:\n{$footer}\n\nPaste this footer into the new document unchanged, including block comments, ids, classes, content, links, and formatting." : null,
            'Create fresh main-page content appropriate for the new page name and brief. Do not duplicate the source page body content except for any exact header/footer provided above.',
            "Source page HTML for design context:\n{$sourceExcerpt}",
            'Return one complete standalone Tailwind HTML document with balanced tw:block markers. Keep all block ids unique within the new page. Do not use markdown or code fences.',
        ])));
    }

    private function sourceExcerpt(string $html): string
    {
        $html = trim($html);

        if ($html === '') {
            return '(source page is empty)';
        }

        if (mb_strlen($html, 'UTF-8') <= 30000) {
            return $html;
        }

        return mb_substr($html, 0, 15000, 'UTF-8')
            ."\n\n<!-- source page truncated for length -->\n\n"
            .mb_substr($html, -12000, null, 'UTF-8');
    }

    private function regionHtml(string $html, string $region): string
    {
        foreach ($this->blocks->index($html) as $block) {
            $haystack = strtolower(implode(' ', [
                (string) ($block['id'] ?? ''),
                (string) ($block['type'] ?? ''),
                (string) ($block['label'] ?? ''),
            ]));

            if (str_contains($haystack, $region)) {
                return trim((string) ($block['html'] ?? ''));
            }
        }

        if (preg_match('/<'.$region.'\b[^>]*>.*?<\/'.$region.'>/is', $html, $match)) {
            return trim($match[0]);
        }

        return '';
    }
}
