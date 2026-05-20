<?php

namespace App\Services\Schema;

class NodeSchemas
{
    public const TYPES = [
        'container', 'stack', 'grid', 'heading', 'text', 'image', 'button', 'badge', 'link',
        'input', 'textarea', 'form_group', 'card', 'icon', 'list', 'list_item', 'divider',
        'element_instance',
    ];

    public const CONTAINER_TYPES = ['container', 'stack', 'grid', 'form_group', 'card', 'list', 'list_item'];

    public static function node(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['id', 'type', 'props', 'locks', 'metadata'],
            'properties' => [
                'id' => ['type' => 'string', 'pattern' => '^(node_|inst_)[0-9a-hjkmnp-tv-z]{26}$', 'maxLength' => 32],
                'type' => ['enum' => self::TYPES],
                'props' => ['type' => 'object'],
                'children' => ['type' => 'array'],
                'locks' => self::locks(),
                'metadata' => self::metadata(),
            ],
        ];
    }

    public static function props(string $type): array
    {
        return match ($type) {
            'container' => self::object([
                'layout' => ['enum' => ['block', 'flex_row', 'flex_col']],
                'gap' => ['enum' => ['none', 'sm', 'md', 'lg']],
                'alignment' => ['enum' => ['start', 'center', 'end', 'stretch']],
                'justification' => ['enum' => ['start', 'center', 'end', 'between']],
                'background' => ['enum' => ['none', 'neutral', 'accent', 'muted']],
                'padding' => ['enum' => ['none', 'sm', 'md', 'lg']],
                'radius' => ['enum' => ['none', 'sm', 'md', 'lg', 'xl', 'full']],
            ], ['layout', 'gap', 'alignment', 'justification', 'background', 'padding', 'radius']),
            'stack' => self::object([
                'gap' => ['enum' => ['none', 'sm', 'md', 'lg', 'xl']],
                'alignment' => ['enum' => ['left', 'center', 'right']],
            ], ['gap', 'alignment']),
            'grid' => self::object([
                'columns' => ['enum' => [1, 2, 3, 4, 6]],
                'gap' => ['enum' => ['sm', 'md', 'lg']],
            ], ['columns', 'gap']),
            'heading' => self::object([
                'level' => ['enum' => [1, 2, 3, 4]],
                'text' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 200],
                'alignment' => ['enum' => ['left', 'center', 'right']],
                'emphasis' => ['enum' => ['default', 'muted', 'accent']],
            ], ['level', 'text', 'alignment', 'emphasis']),
            'text' => self::object([
                'text' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 2000],
                'size' => ['enum' => ['xs', 'sm', 'base', 'lg', 'xl']],
                'alignment' => ['enum' => ['left', 'center', 'right']],
                'emphasis' => ['enum' => ['default', 'muted', 'accent']],
            ], ['text', 'size', 'alignment', 'emphasis']),
            'image' => self::object([
                'src' => ['type' => 'string', 'minLength' => 1],
                'alt' => ['type' => 'string'],
                'width' => ['type' => ['number', 'integer', 'null']],
                'height' => ['type' => ['number', 'integer', 'null']],
                'fit' => ['enum' => ['cover', 'contain', 'none']],
                'radius' => ['enum' => ['none', 'sm', 'md', 'lg', 'full']],
            ], ['src', 'alt', 'width', 'height', 'fit', 'radius']),
            'button' => self::object([
                'label' => ['type' => 'string', 'minLength' => 1],
                'href' => ['type' => 'string'],
                'variant' => ['enum' => ['primary', 'secondary', 'ghost']],
                'size' => ['enum' => ['sm', 'md', 'lg']],
            ], ['label', 'href', 'variant', 'size']),
            'badge' => self::object([
                'label' => ['type' => 'string', 'minLength' => 1],
                'tone' => ['enum' => ['neutral', 'positive', 'warning', 'info', 'accent']],
            ], ['label', 'tone']),
            'link' => self::object([
                'label' => ['type' => 'string', 'minLength' => 1],
                'href' => ['type' => 'string'],
                'emphasis' => ['enum' => ['default', 'underline', 'muted']],
            ], ['label', 'href', 'emphasis']),
            'input' => self::object([
                'name' => ['type' => 'string', 'minLength' => 1],
                'input_type' => ['enum' => ['text', 'email', 'tel', 'url', 'number']],
                'placeholder' => ['type' => 'string'],
                'required' => ['type' => 'boolean'],
            ], ['name', 'input_type', 'placeholder', 'required']),
            'textarea' => self::object([
                'name' => ['type' => 'string', 'minLength' => 1],
                'placeholder' => ['type' => 'string'],
                'rows' => ['type' => 'integer', 'minimum' => 2, 'maximum' => 12],
                'required' => ['type' => 'boolean'],
            ], ['name', 'placeholder', 'rows', 'required']),
            'form_group' => self::object(['layout' => ['enum' => ['stacked', 'inline']]], ['layout']),
            'card' => self::object([
                'variant' => ['enum' => ['elevated', 'outlined', 'filled']],
                'padding' => ['enum' => ['sm', 'md', 'lg']],
            ], ['variant', 'padding']),
            'icon' => self::object([
                'name' => ['type' => 'string', 'minLength' => 1],
                'size' => ['enum' => ['sm', 'md', 'lg', 'xl']],
                'tone' => ['enum' => ['default', 'muted', 'accent']],
            ], ['name', 'size', 'tone']),
            'list' => self::object(['style' => ['enum' => ['bulleted', 'numbered', 'checked']]], ['style']),
            'list_item' => self::object([], []),
            'divider' => self::object([
                'weight' => ['enum' => ['thin', 'medium']],
                'spacing' => ['enum' => ['sm', 'md', 'lg']],
            ], ['weight', 'spacing']),
            'element_instance' => self::object([
                'library_id' => ['type' => 'string', 'pattern' => '^elem_[0-9a-hjkmnp-tv-z]{26}$', 'maxLength' => 32],
                'overrides' => ['type' => 'object'],
            ], ['library_id', 'overrides']),
            default => ['not' => []],
        };
    }

    public static function locks(): array
    {
        return self::object([
            'content_locked' => ['type' => 'boolean'],
            'style_locked' => ['type' => 'boolean'],
            'layout_locked' => ['type' => 'boolean'],
        ], ['content_locked', 'style_locked', 'layout_locked']);
    }

    public static function metadata(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['created_by', 'created_at', 'updated_at'],
            'properties' => [
                'created_by' => ['enum' => ['planner', 'generator', 'edit', 'library_instance', 'user']],
                'created_at' => ['type' => 'string', 'format' => 'date-time'],
                'updated_at' => ['type' => 'string', 'format' => 'date-time'],
                'source_library_id' => ['type' => 'string'],
            ],
        ];
    }

    private static function object(array $properties, array $required): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => $required,
            'properties' => $properties,
        ];
    }
}
