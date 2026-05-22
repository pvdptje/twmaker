<?php

namespace App\Services\Html;

class HtmlSafetySanitizer
{
    public function sanitize(string $html): string
    {
        $html = $this->removeInlineEventHandlers($html);

        return $this->neutralizeJavascriptUrls($html);
    }

    private function removeInlineEventHandlers(string $html): string
    {
        return preg_replace('/\s+on[a-z]+\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? $html;
    }

    private function neutralizeJavascriptUrls(string $html): string
    {
        return preg_replace(
            '/(\s(?:href|src|action|formaction)\s*=\s*)(["\'])\s*javascript\s*:[^"\']*\2/i',
            '$1$2#$2',
            $html,
        ) ?? $html;
    }
}
