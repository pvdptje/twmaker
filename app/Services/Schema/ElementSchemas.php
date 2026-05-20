<?php

namespace App\Services\Schema;

class ElementSchemas
{
    public const TYPES = [
        'primary_button',
        'secondary_button',
        'pill_badge',
        'feature_card',
        'testimonial_card',
        'stat_card',
        'nav_link_group',
        'cta_group',
    ];

    public static function definition(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['id', 'project_id', 'name', 'type', 'default_props', 'preview_html_cache', 'created_at', 'updated_at'],
            'properties' => [
                'id' => self::typedId('elem_'),
                'project_id' => self::typedId('proj_'),
                'name' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 120],
                'type' => ['enum' => self::TYPES],
                'default_props' => ['type' => 'object'],
                'preview_html_cache' => ['type' => ['string', 'null']],
                'created_at' => ['type' => 'string', 'format' => 'date-time'],
                'updated_at' => ['type' => 'string', 'format' => 'date-time'],
            ],
        ];
    }

    public static function props(string $type, bool $partial = false): array|bool
    {
        $schemas = [
            'primary_button' => self::buttonProps(),
            'secondary_button' => self::buttonProps(),
            'pill_badge' => self::object([
                'label' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 30],
                'tone' => ['enum' => ['neutral', 'positive', 'warning', 'info', 'accent']],
                'leading_icon' => ['type' => ['string', 'null']],
            ], ['label', 'tone', 'leading_icon']),
            'feature_card' => self::object([
                'icon' => ['type' => ['string', 'null']],
                'heading' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 80],
                'body' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 400],
                'link' => [
                    'oneOf' => [
                        ['type' => 'null'],
                        self::object([
                            'label' => ['type' => 'string', 'minLength' => 1],
                            'href' => ['type' => 'string'],
                        ], ['label', 'href']),
                    ],
                ],
            ], ['icon', 'heading', 'body', 'link']),
            'testimonial_card' => self::object([
                'quote' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 500],
                'author_name' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 80],
                'author_title' => ['type' => ['string', 'null']],
                'author_avatar_url' => ['type' => ['string', 'null']],
                'rating' => ['enum' => [1, 2, 3, 4, 5, null]],
            ], ['quote', 'author_name', 'author_title', 'author_avatar_url', 'rating']),
            'stat_card' => self::object([
                'value' => ['type' => 'string', 'minLength' => 1],
                'label' => ['type' => 'string', 'minLength' => 1],
                'trend' => ['enum' => ['up', 'down', 'flat', null]],
                'trend_label' => ['type' => ['string', 'null']],
            ], ['value', 'label', 'trend', 'trend_label']),
            'nav_link_group' => self::object([
                'links' => [
                    'type' => 'array',
                    'minItems' => 1,
                    'maxItems' => 8,
                    'items' => self::object([
                        'label' => ['type' => 'string', 'minLength' => 1],
                        'href' => ['type' => 'string'],
                        'active' => ['type' => 'boolean'],
                    ], ['label', 'href', 'active']),
                ],
                'layout' => ['enum' => ['horizontal', 'vertical']],
            ], ['links', 'layout']),
            'cta_group' => self::object([
                'primary' => self::linkOrNull(),
                'secondary' => self::linkOrNull(),
                'alignment' => ['enum' => ['left', 'center', 'right']],
            ], ['primary', 'secondary', 'alignment']),
        ];

        $schema = $schemas[$type] ?? false;

        if ($partial && is_array($schema) && isset($schema['required'])) {
            $schema['required'] = [];
        }

        return $schema;
    }

    private static function buttonProps(): array
    {
        return self::object([
            'label' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 40],
            'href' => ['type' => 'string'],
            'size' => ['enum' => ['sm', 'md', 'lg']],
            'icon' => ['type' => ['string', 'null']],
            'icon_position' => ['enum' => ['leading', 'trailing']],
        ], ['label', 'href', 'size', 'icon', 'icon_position']);
    }

    private static function linkOrNull(): array
    {
        return [
            'oneOf' => [
                ['type' => 'null'],
                self::object([
                    'label' => ['type' => 'string', 'minLength' => 1],
                    'href' => ['type' => 'string'],
                ], ['label', 'href']),
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

    private static function typedId(string $prefix): array
    {
        return ['type' => 'string', 'pattern' => '^'.$prefix.'[0-9a-hjkmnp-tv-z]{26}$', 'maxLength' => 32];
    }
}
