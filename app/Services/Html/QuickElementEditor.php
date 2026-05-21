<?php

namespace App\Services\Html;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

class QuickElementEditor
{
    public function __construct(
        private readonly BlockIndexer $blocks,
        private readonly HtmlDocumentValidator $validator,
    ) {}

    public function replace(string $htmlSource, string $editId, string $replacementHtml): string
    {
        [$blockId, $path] = $this->parseEditId($editId);
        $replacementHtml = trim($replacementHtml);

        if (preg_match('/<!--\s*\/?\s*tw:block\b/i', $replacementHtml)) {
            throw new HtmlValidationException(['Quick edit HTML must not include block marker comments.']);
        }

        $replacement = $this->singleReplacementElement($replacementHtml);

        foreach ($this->blocks->index($htmlSource) as $block) {
            if ($block['id'] !== $blockId) {
                continue;
            }

            $updatedBlock = $this->replaceInsideBlock($block['html'], $path, $replacement);
            $updatedHtml = substr($htmlSource, 0, $block['start_offset'])
                .$updatedBlock
                .substr($htmlSource, $block['end_offset']);

            $this->validator->assertValid($updatedHtml);

            return $updatedHtml;
        }

        throw new HtmlValidationException(["Block [{$blockId}] was not found."]);
    }

    /**
     * @return array{0: string, 1: array<int, int>}
     */
    private function parseEditId(string $editId): array
    {
        if (! preg_match('/^([^:]+):(.*)$/', $editId, $match)) {
            throw new HtmlValidationException(['Quick edit target is invalid.']);
        }

        $path = $match[2] === ''
            ? []
            : array_map('intval', explode('.', $match[2]));

        return [$match[1], $path];
    }

    private function replaceInsideBlock(string $blockHtml, array $path, string $replacementHtml): string
    {
        if (! preg_match('/^(<!--\s*tw:block\b[^>]*-->)(.*)(<!--\s*\/tw:block\s*-->)$/is', $blockHtml, $match)) {
            throw new HtmlValidationException(['Selected block is malformed.']);
        }

        $document = $this->loadFragment('<div id="tw-quick-edit-root">'.$match[2].'</div>');
        $root = (new DOMXPath($document))->query('//*[@id="tw-quick-edit-root"]')->item(0);

        if (! $root instanceof DOMElement) {
            throw new HtmlValidationException(['Selected block could not be parsed.']);
        }

        $target = $this->elementAtPath($root, $path);
        if (! $target instanceof DOMElement) {
            throw new HtmlValidationException(['Selected element was not found.']);
        }

        $replacement = $this->elementChildren($this->body($this->loadFragment($replacementHtml)))[0] ?? null;
        if (! $replacement instanceof DOMElement) {
            throw new HtmlValidationException(['Replacement HTML must be one complete element.']);
        }

        $target->parentNode?->replaceChild($document->importNode($replacement, true), $target);

        return $match[1].$this->innerHtml($root).$match[3];
    }

    private function elementAtPath(DOMElement $root, array $path): ?DOMElement
    {
        $current = $this->elementChildren($root)[0] ?? null;

        foreach ($path as $index) {
            if (! $current instanceof DOMElement) {
                return null;
            }

            $current = $this->elementChildren($current)[$index] ?? null;
        }

        return $current instanceof DOMElement ? $current : null;
    }

    /**
     * @return array<int, DOMElement>
     */
    private function elementChildren(DOMNode $node): array
    {
        $children = [];

        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMElement) {
                $children[] = $child;
            }
        }

        return $children;
    }

    private function singleReplacementElement(string $html): string
    {
        $document = $this->loadFragment($html);
        $elements = [];

        foreach ($this->body($document)->childNodes as $child) {
            if ($child instanceof DOMElement) {
                $elements[] = $child;

                continue;
            }

            if (trim((string) $child->textContent) !== '') {
                throw new HtmlValidationException(['Replacement HTML must be one complete element.']);
            }
        }

        if (count($elements) !== 1) {
            throw new HtmlValidationException(['Replacement HTML must be one complete element.']);
        }

        return $document->saveHTML($elements[0]) ?: '';
    }

    private function loadFragment(string $html): DOMDocument
    {
        $document = new DOMDocument('1.0', 'UTF-8');

        $previous = libxml_use_internal_errors(true);
        $document->loadHTML(
            '<!doctype html><html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head><body>'.$html.'</body></html>',
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $document;
    }

    private function body(DOMDocument $document): DOMElement
    {
        $body = $document->getElementsByTagName('body')->item(0);

        if (! $body instanceof DOMElement) {
            throw new HtmlValidationException(['HTML could not be parsed.']);
        }

        return $body;
    }

    private function innerHtml(DOMElement $element): string
    {
        $html = '';

        foreach ($element->childNodes as $child) {
            $html .= $element->ownerDocument?->saveHTML($child) ?: '';
        }

        return $html;
    }
}
