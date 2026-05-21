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

        if (! in_array($type, ['header', 'hero', 'feature_grid', 'feature_split', 'stats_band', 'testimonial_grid', 'faq', 'logo_cloud', 'cta_band', 'contact_form', 'footer'], true)) {
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
            'features', 'feature_cards' => 'feature_grid',
            'stats', 'numbers' => 'stats_band',
            'testimonials', 'reviews' => 'testimonial_grid',
            'cta', 'call_to_action' => 'cta_band',
            'contact' => 'contact_form',
            'navigation', 'nav' => 'header',
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
            'header' => [
                'variant' => $this->enum($props['variant'] ?? null, ['simple', 'with_cta', 'centered'], 'with_cta'),
                'sticky' => (bool) ($props['sticky'] ?? false),
            ],
            'feature_grid' => [
                'columns' => $this->integerEnum($props['columns'] ?? null, [2, 3, 4], 3),
            ],
            'feature_split' => [
                'image_side' => $this->enum($props['image_side'] ?? null, ['left', 'right'], 'right'),
                'image_url' => is_string($props['image_url'] ?? null) && $props['image_url'] !== '' ? $props['image_url'] : 'placeholder:feature',
            ],
            'stats_band' => [
                'columns' => $this->instanceColumnCount($children, 2, 4, $this->integerEnum($props['columns'] ?? null, [2, 3, 4], 3)),
            ],
            'testimonial_grid' => [
                'columns' => $this->integerEnum($props['columns'] ?? null, [1, 2, 3], 3),
            ],
            'faq' => [
                'layout' => $this->enum($props['layout'] ?? null, ['single_column', 'two_column'], 'single_column'),
            ],
            'cta_band' => [
                'variant' => $this->enum($props['variant'] ?? null, ['centered', 'split'], 'centered'),
            ],
            'contact_form' => [
                'submit_endpoint' => is_string($props['submit_endpoint'] ?? null) ? $props['submit_endpoint'] : null,
            ],
            'footer' => [
                'variant' => $this->enum($props['variant'] ?? null, ['simple', 'columned'], 'simple'),
                'columns' => $this->instanceColumnCount($children, 1, 4, 1),
            ],
            default => [],
        };
    }

    private function sectionChildren(string $type, array $children): array
    {
        return match ($type) {
            'header' => $this->headerChildren($children),
            'hero' => $this->heroChildren($children),
            'feature_grid' => $this->featureGridChildren($children),
            'feature_split' => $this->featureSplitChildren($children),
            'stats_band' => $this->statsBandChildren($children),
            'testimonial_grid' => $this->testimonialGridChildren($children),
            'faq' => $this->faqChildren($children),
            'logo_cloud' => $this->logoCloudChildren($children),
            'cta_band' => $this->ctaBandChildren($children),
            'contact_form' => $this->contactFormChildren($children),
            'footer' => $this->footerChildren($children),
            default => $children,
        };
    }

    private function headerChildren(array $children): array
    {
        $logo = $this->firstOf($children, 'image') ?? $this->fallbackImage('placeholder:logo', 'Logo');
        $instances = array_values(array_filter($children, fn (array $child): bool => $child['type'] === 'element_instance'));

        return array_values(array_filter(array_merge([$logo], array_slice($instances, 0, 2))));
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
        $instance = $this->firstOf($children, 'element_instance');

        return array_values(array_filter([$heading, $text, $list, $instance]));
    }

    private function featureGridChildren(array $children): array
    {
        $heading = $this->firstOf($children, 'heading');
        if ($heading !== null) {
            $heading['props']['level'] = 2;
        }

        $text = $this->firstOf($children, 'text');
        $instances = array_values(array_filter($children, fn (array $child): bool => $child['type'] === 'element_instance'));

        return array_values(array_filter(array_merge([$heading, $text], array_slice($instances, 0, 12))));
    }

    private function statsBandChildren(array $children): array
    {
        $heading = $this->firstOf($children, 'heading');
        if ($heading !== null) {
            $heading['props']['level'] = 2;
        }

        $instances = array_values(array_filter($children, fn (array $child): bool => $child['type'] === 'element_instance'));

        return array_values(array_filter(array_merge([$heading], array_slice($instances, 0, 4))));
    }

    private function testimonialGridChildren(array $children): array
    {
        $heading = $this->firstOf($children, 'heading');
        if ($heading !== null) {
            $heading['props']['level'] = 2;
        }

        $instances = array_values(array_filter($children, fn (array $child): bool => $child['type'] === 'element_instance'));

        return array_values(array_filter(array_merge([$heading], array_slice($instances, 0, 9))));
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

    private function ctaBandChildren(array $children): array
    {
        $heading = $this->firstOf($children, 'heading') ?? $this->fallbackHeading('Ready to get started?', 2);
        $heading['props']['level'] = 2;
        $text = $this->firstOf($children, 'text');
        $instance = $this->firstOf($children, 'element_instance');

        return array_values(array_filter([$heading, $text, $instance]));
    }

    private function contactFormChildren(array $children): array
    {
        $heading = $this->firstOf($children, 'heading') ?? $this->fallbackHeading('Contact us', 2);
        $heading['props']['level'] = 2;
        $text = $this->firstOf($children, 'text');
        $formGroups = array_values(array_filter($children, fn (array $child): bool => $child['type'] === 'form_group'));
        $instance = $this->firstOf($children, 'element_instance');

        return array_values(array_filter(array_merge([$heading, $text], array_slice($formGroups, 0, 6), [$instance])));
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
            'metadata' => $this->metadata([], 'generator'),
        ];
    }

    private function fallbackText(string $text): array
    {
        return [
            'id' => $this->ids->node(),
            'type' => 'text',
            'props' => ['text' => $text, 'size' => 'base', 'alignment' => 'left', 'emphasis' => 'default'],
            'metadata' => $this->metadata([], 'generator'),
        ];
    }

    private function fallbackImage(string $src, string $alt): array
    {
        return [
            'id' => $this->ids->node(),
            'type' => 'image',
            'props' => ['src' => $src, 'alt' => $alt, 'width' => null, 'height' => null, 'fit' => 'contain', 'radius' => 'none'],
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

    private function integerEnum(mixed $value, array $allowed, int $fallback): int
    {
        $value = is_numeric($value) ? (int) $value : $value;

        return in_array($value, $allowed, true) ? $value : $fallback;
    }

    private function instanceColumnCount(array $children, int $minimum, int $maximum, int $fallback): int
    {
        $instances = count(array_filter($children, fn (array $child): bool => ($child['type'] ?? null) === 'element_instance'));

        if ($instances === 0) {
            return $fallback;
        }

        return max($minimum, min($maximum, $instances));
    }

    private function validId(mixed $id, string $prefix): bool
    {
        return is_string($id) && preg_match('/^'.preg_quote($prefix, '/').'[0-9a-hjkmnp-tv-z]{26}$/', $id) === 1;
    }
}
