<?php

namespace App\Services\Rendering;

use App\Services\Schema\SchemaValidationException;
use App\Services\Schema\SchemaValidator;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Support\Arr;

class Renderer
{
    public function __construct(
        private readonly SchemaValidator $validator,
        private readonly TailwindClassMap $classes,
        private readonly ViewFactory $views,
    ) {}

    public function renderDocument(array $document, array $library = []): string
    {
        if (! $this->validator->validateDocument($document)) {
            throw new SchemaValidationException($this->validator->errors());
        }

        return $this->views->make('render.document', [
            'document' => $document,
            'library' => $library,
            'renderer' => $this,
            'classes' => $this->classes,
        ])->render();
    }

    public function renderPreviewDocument(array $document, array $library = []): string
    {
        $body = $this->renderDocument($document, $library);
        $title = e($document['page_metadata']['title'] ?? 'Preview');

        return <<<HTML
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{$title}</title>
<link rel="stylesheet" href="/preview.css">
<script src="https://cdn.tailwindcss.com"></script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body>
{$body}
<script src="/preview-bridge.js"></script>
</body>
</html>
HTML;
    }

    public function renderPreviewHtml(string $htmlSource, string $title = 'Preview'): string
    {
        if ($this->isFullHtmlDocument($htmlSource)) {
            return $this->injectPreviewBridge($htmlSource);
        }

        $title = e($title);

        return <<<HTML
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{$title}</title>
<link rel="stylesheet" href="/preview.css">
<script src="https://cdn.tailwindcss.com"></script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body>
{$htmlSource}
<script src="/preview-bridge.js"></script>
</body>
</html>
HTML;
    }

    public function renderDownloadHtml(string $htmlSource, string $title = 'Preview'): string
    {
        if ($this->isFullHtmlDocument($htmlSource)) {
            return $htmlSource;
        }

        $title = e($title);

        return <<<HTML
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{$title}</title>
<script src="https://cdn.tailwindcss.com"></script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<style>
body {
    margin: 0;
    background: #fff;
    color: #171717;
    font-family: ui-sans-serif, system-ui, sans-serif;
}
</style>
</head>
<body>
{$htmlSource}
</body>
</html>
HTML;
    }

    public function renderSection(array $section, array $library): string
    {
        return $this->views->make('render.sections.'.$section['type'], [
            'section' => $section,
            'library' => $library,
            'renderer' => $this,
            'classes' => $this->classes,
        ])->render();
    }

    public function renderNode(array $node, array $library): string
    {
        if (($node['type'] ?? null) === 'element_instance') {
            return $this->renderElementInstance($node, $library);
        }

        return $this->views->make('render.nodes.'.$node['type'], [
            'node' => $node,
            'library' => $library,
            'renderer' => $this,
            'classes' => $this->classes,
        ])->render();
    }

    public function renderChildren(array $children, array $library): string
    {
        return collect($children)->map(fn (array $child): string => $this->renderNode($child, $library))->implode('');
    }

    public function renderElementInstance(array $instance, array $library): string
    {
        $definition = $library[$instance['props']['library_id']] ?? null;
        if ($definition === null) {
            throw new SchemaValidationException(["{$instance['id']}: missing reusable element definition"]);
        }

        $type = $definition['type'];
        $props = array_replace_recursive($definition['default_props'] ?? [], $instance['props']['overrides'] ?? []);

        return $this->views->make('render.elements.'.$type, [
            'instance' => $instance,
            'type' => $type,
            'props' => $props,
            'renderer' => $this,
            'classes' => $this->classes,
        ])->render();
    }

    public function styleAttr(array $props): string
    {
        $styles = [];
        if (($props['width'] ?? null) !== null) {
            $styles[] = 'width: '.((int) $props['width']).'px';
        }
        if (($props['height'] ?? null) !== null) {
            $styles[] = 'height: '.((int) $props['height']).'px';
        }

        return $styles === [] ? '' : 'style="'.e(implode('; ', $styles)).'"';
    }

    public function placeholderSrc(string $src): string
    {
        if (! str_starts_with($src, 'placeholder:')) {
            return $src;
        }

        $label = rawurlencode(Arr::last(explode(':', $src)) ?: 'image');

        return "https://placehold.co/960x540/f5f5f5/404040?text={$label}";
    }

    private function isFullHtmlDocument(string $html): bool
    {
        return preg_match('/<\s*html\b/i', $html) === 1
            && preg_match('/<\s*body\b/i', $html) === 1;
    }

    private function injectPreviewBridge(string $html): string
    {
        if (str_contains($html, '/preview-bridge.js')) {
            return $html;
        }

        $bridge = "\n".'<script src="/preview-bridge.js"></script>'."\n";

        if (preg_match('/<\s*\/\s*body\s*>/i', $html)) {
            return preg_replace('/<\s*\/\s*body\s*>/i', $bridge.'</body>', $html, 1) ?? $html;
        }

        return $html.$bridge;
    }
}
