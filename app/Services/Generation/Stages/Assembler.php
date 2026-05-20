<?php

namespace App\Services\Generation\Stages;

use App\Services\Ids\IdGenerator;

class Assembler
{
    public function __construct(private readonly IdGenerator $ids) {}

    public function assemble(array $document): array
    {
        foreach ($document['document_tree'] ?? [] as $index => $section) {
            $normalized = $this->normalizeSection($section);

            if ($normalized === null) {
                unset($document['document_tree'][$index]);

                continue;
            }

            $document['document_tree'][$index] = $normalized;
        }

        $document['document_tree'] = array_values($document['document_tree'] ?? []);

        return $document;
    }

    private function normalizeSection(array $section): ?array
    {
        $type = $section['type'] ?? null;

        if (! is_string($type)) {
            return null;
        }

        $type = $this->sectionType($type);

        if (! in_array($type, ['hero', 'feature_split', 'faq', 'logo_cloud', 'footer'], true)) {
            return null;
        }

        $children = array_values(array_filter(array_map(
            fn (mixed $child): ?array => is_array($child) ? $this->normalizeNode($child) : null,
            $section['children'] ?? [],
        )));

        return [
            'id' => $this->validId($section['id'] ?? null, 'sec_') ? $section['id'] : $this->ids->section(),
            'type' => $type,
            'props' => $this->sectionProps($type, is_array($section['props'] ?? null) ? $section['props'] : [], $children),
            'children' => $this->sectionChildren($type, $children),
            'locks' => $this->locks($section['locks'] ?? []),
            'metadata' => $this->metadata($section['metadata'] ?? [], 'generator'),
        ];
    }

    private function normalizeNode(array $node): ?array
    {
        $type = $this->nodeType($node['type'] ?? null);

        if ($type === null) {
            return null;
        }

        $props = is_array($node['props'] ?? null) ? $node['props'] : [];
        $children = array_values(array_filter(array_map(
            fn (mixed $child): ?array => is_array($child) ? $this->normalizeNode($child) : null,
            $node['children'] ?? [],
        )));
        $idPrefix = $type === 'element_instance' ? 'inst_' : 'node_';

        $normalized = [
            'id' => $this->validId($node['id'] ?? null, $idPrefix) ? $node['id'] : ($type === 'element_instance' ? $this->ids->elementInstance() : $this->ids->node()),
            'type' => $type,
            'props' => $this->nodeProps($type, $props, $node),
            'locks' => $this->locks($node['locks'] ?? []),
            'metadata' => $this->metadata($node['metadata'] ?? [], $type === 'element_instance' ? 'library_instance' : 'generator'),
        ];

        if (in_array($type, ['container', 'stack', 'grid', 'form_group', 'card', 'list', 'list_item'], true)) {
            $normalized['children'] = $children;
        }

        return $normalized;
    }

    private function sectionType(string $type): string
    {
        return match ($type) {
            'feature', 'feature_section', 'feature_highlight' => 'feature_split',
            'logos', 'logo_strip' => 'logo_cloud',
            default => $type,
        };
    }

    private function nodeType(mixed $type): ?string
    {
        return match ($type) {
            'heading', 'headline', 'title', 'section_title' => 'heading',
            'text', 'paragraph', 'subtitle', 'body', 'copy', 'description' => 'text',
            'image', 'logo' => 'image',
            'badge', 'pill' => 'badge',
            'list', 'bullets' => 'list',
            'list_item', 'bullet' => 'list_item',
            'button', 'link', 'divider', 'icon', 'container', 'stack', 'grid', 'form_group', 'card', 'input', 'textarea', 'element_instance' => $type,
            default => null,
        };
    }

    private function sectionProps(string $type, array $props, array $children): array
    {
        $common = [
            'background' => $this->enum($props['background'] ?? null, ['default', 'neutral', 'inverse', 'accent', 'muted'], 'default'),
            'padding' => $this->enum($props['padding'] ?? null, ['sm', 'md', 'lg', 'xl'], 'lg'),
            'max_width' => $this->enum($props['max_width'] ?? null, ['narrow', 'default', 'wide', 'full'], 'default'),
            'alignment' => $this->enum($props['alignment'] ?? null, ['left', 'center'], 'left'),
        ];

        return $common + match ($type) {
            'hero' => [
                'variant' => $this->enum($props['variant'] ?? null, ['centered', 'split_left_image', 'split_right_image', 'background_image'], 'centered'),
                'image_url' => is_string($props['image_url'] ?? null) ? $props['image_url'] : null,
            ],
            'feature_split' => [
                'image_side' => $this->enum($props['image_side'] ?? null, ['left', 'right'], 'right'),
                'image_url' => is_string($props['image_url'] ?? null) && $props['image_url'] !== '' ? $props['image_url'] : 'placeholder:feature',
            ],
            'faq' => [
                'layout' => $this->enum($props['layout'] ?? null, ['single_column', 'two_column'], 'single_column'),
            ],
            'footer' => [
                'variant' => $this->enum($props['variant'] ?? null, ['simple', 'columned'], 'simple'),
                'columns' => $props['columns'] ?? count(array_filter($children, fn (array $child): bool => ($child['type'] ?? null) === 'element_instance')),
            ],
            default => [],
        };
    }

    private function sectionChildren(string $type, array $children): array
    {
        return match ($type) {
            'hero' => $this->heroChildren($children),
            'feature_split' => $this->featureSplitChildren($children),
            'faq' => $this->faqChildren($children),
            'logo_cloud' => $this->logoCloudChildren($children),
            'footer' => $this->footerChildren($children),
            default => $children,
        };
    }

    private function heroChildren(array $children): array
    {
        $heading = $this->firstOf($children, 'heading') ?? $this->fallbackHeading('Your landing page', 1);
        $heading['props']['level'] = 1;
        $text = $this->firstOf($children, 'text') ?? $this->fallbackText('A concise introduction for visitors.');
        $badge = $this->firstOf($children, 'badge');
        $image = $this->firstOf($children, 'image');

        return array_values(array_filter([$badge, $heading, $text, $image]));
    }

    private function featureSplitChildren(array $children): array
    {
        $heading = $this->firstOf($children, 'heading') ?? $this->fallbackHeading('Key feature', 2);
        $heading['props']['level'] = 2;
        $text = $this->firstOf($children, 'text') ?? $this->fallbackText('A short explanation of the feature.');
        $list = $this->firstOf($children, 'list');

        return array_values(array_filter([$heading, $text, $list]));
    }

    private function faqChildren(array $children): array
    {
        $heading = $this->firstOf($children, 'heading') ?? $this->fallbackHeading('Questions', 2);
        $heading['props']['level'] = 2;

        $pairs = array_values(array_filter($children, fn (array $child): bool => in_array($child['type'], ['heading', 'text'], true)));

        if (count($pairs) < 6) {
            $pairs = [
                $this->fallbackHeading('What is included?', 3),
                $this->fallbackText('A focused first draft that you can edit in the builder.'),
                $this->fallbackHeading('Can I customize it?', 3),
                $this->fallbackText('Yes. Select sections and request targeted edits.'),
                $this->fallbackHeading('Can I export it?', 3),
                $this->fallbackText('Yes. Export produces plain HTML and Tailwind CSS.'),
            ];
        }

        return array_merge([$heading], array_slice($pairs, 0, 24));
    }

    private function logoCloudChildren(array $children): array
    {
        $heading = $this->firstOf($children, 'heading');
        if ($heading !== null) {
            $heading['props']['level'] = 2;
        }

        $images = array_values(array_filter($children, fn (array $child): bool => $child['type'] === 'image'));
        while (count($images) < 4) {
            $images[] = $this->fallbackImage('placeholder:logo', 'Logo');
        }

        return array_values(array_filter(array_merge([$heading], array_slice($images, 0, 8))));
    }

    private function footerChildren(array $children): array
    {
        $logo = $this->firstOf($children, 'image') ?? $this->fallbackImage('placeholder:logo', 'Logo');
        $texts = array_values(array_filter($children, fn (array $child): bool => $child['type'] === 'text'));
        $instances = array_values(array_filter($children, fn (array $child): bool => $child['type'] === 'element_instance'));

        return array_values(array_filter(array_merge(
            [$logo],
            array_slice($texts, 0, 1),
            $instances,
            array_slice($texts, 1, 1),
        )));
    }

    private function nodeProps(string $type, array $props, array $node): array
    {
        return match ($type) {
            'heading' => [
                'level' => in_array($props['level'] ?? null, [1, 2, 3, 4], true) ? $props['level'] : 2,
                'text' => $this->textValue($props, $node, 'Heading'),
                'alignment' => $this->enum($props['alignment'] ?? null, ['left', 'center', 'right'], 'left'),
                'emphasis' => $this->enum($props['emphasis'] ?? null, ['default', 'muted', 'accent'], 'default'),
            ],
            'text' => [
                'text' => $this->textValue($props, $node, 'Text'),
                'size' => $this->enum($props['size'] ?? null, ['xs', 'sm', 'base', 'lg', 'xl'], 'base'),
                'alignment' => $this->enum($props['alignment'] ?? null, ['left', 'center', 'right'], 'left'),
                'emphasis' => $this->enum($props['emphasis'] ?? null, ['default', 'muted', 'accent'], 'default'),
            ],
            'image' => [
                'src' => is_string($props['src'] ?? null) && $props['src'] !== '' ? $props['src'] : 'placeholder:image',
                'alt' => is_string($props['alt'] ?? null) ? $props['alt'] : 'Image',
                'width' => is_numeric($props['width'] ?? null) ? $props['width'] : null,
                'height' => is_numeric($props['height'] ?? null) ? $props['height'] : null,
                'fit' => $this->enum($props['fit'] ?? null, ['cover', 'contain', 'none'], 'cover'),
                'radius' => $this->enum($props['radius'] ?? null, ['none', 'sm', 'md', 'lg', 'full'], 'md'),
            ],
            'badge' => [
                'label' => $this->textValue($props, $node, 'New'),
                'tone' => $this->enum($props['tone'] ?? null, ['neutral', 'positive', 'warning', 'info', 'accent'], 'accent'),
            ],
            'list' => ['style' => $this->enum($props['style'] ?? null, ['bulleted', 'numbered', 'checked'], 'checked')],
            'list_item' => [],
            default => $props,
        };
    }

    private function firstOf(array $children, string $type): ?array
    {
        foreach ($children as $child) {
            if (($child['type'] ?? null) === $type) {
                return $child;
            }
        }

        return null;
    }

    private function fallbackHeading(string $text, int $level): array
    {
        return [
            'id' => $this->ids->node(),
            'type' => 'heading',
            'props' => ['level' => $level, 'text' => $text, 'alignment' => 'left', 'emphasis' => 'default'],
            'locks' => $this->locks([]),
            'metadata' => $this->metadata([], 'generator'),
        ];
    }

    private function fallbackText(string $text): array
    {
        return [
            'id' => $this->ids->node(),
            'type' => 'text',
            'props' => ['text' => $text, 'size' => 'base', 'alignment' => 'left', 'emphasis' => 'default'],
            'locks' => $this->locks([]),
            'metadata' => $this->metadata([], 'generator'),
        ];
    }

    private function fallbackImage(string $src, string $alt): array
    {
        return [
            'id' => $this->ids->node(),
            'type' => 'image',
            'props' => ['src' => $src, 'alt' => $alt, 'width' => null, 'height' => null, 'fit' => 'contain', 'radius' => 'none'],
            'locks' => $this->locks([]),
            'metadata' => $this->metadata([], 'generator'),
        ];
    }

    private function textValue(array $props, array $node, string $fallback): string
    {
        foreach (['text', 'label', 'title', 'headline', 'content', 'body', 'description'] as $key) {
            $value = $props[$key] ?? $node[$key] ?? null;
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return $fallback;
    }

    private function locks(mixed $locks): array
    {
        $locks = is_array($locks) ? $locks : [];

        return [
            'content_locked' => (bool) ($locks['content_locked'] ?? false),
            'style_locked' => (bool) ($locks['style_locked'] ?? false),
            'layout_locked' => (bool) ($locks['layout_locked'] ?? false),
        ];
    }

    private function metadata(mixed $metadata, string $createdBy): array
    {
        $metadata = is_array($metadata) ? $metadata : [];
        $now = now('UTC')->format('Y-m-d\TH:i:s\Z');

        return [
            'created_by' => $this->enum($metadata['created_by'] ?? null, ['planner', 'generator', 'edit', 'library_instance', 'user'], $createdBy),
            'created_at' => is_string($metadata['created_at'] ?? null) ? $metadata['created_at'] : $now,
            'updated_at' => is_string($metadata['updated_at'] ?? null) ? $metadata['updated_at'] : $now,
        ];
    }

    private function enum(mixed $value, array $allowed, mixed $fallback): mixed
    {
        return in_array($value, $allowed, true) ? $value : $fallback;
    }

    private function validId(mixed $id, string $prefix): bool
    {
        return is_string($id) && preg_match('/^'.preg_quote($prefix, '/').'[0-9a-hjkmnp-tv-z]{26}$/', $id) === 1;
    }
}
