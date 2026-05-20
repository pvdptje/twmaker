<?php

namespace App\Services\Schema;

class SectionSchemas
{
    public const TYPES = [
        'header',
        'hero',
        'logo_cloud',
        'feature_grid',
        'feature_split',
        'stats_band',
        'testimonial_grid',
        'faq',
        'cta_band',
        'contact_form',
        'footer',
    ];

    public static function section(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['id', 'type', 'props', 'children', 'locks', 'metadata'],
            'properties' => [
                'id' => ['type' => 'string', 'pattern' => '^sec_[0-9a-hjkmnp-tv-z]{26}$', 'maxLength' => 32],
                'type' => ['enum' => self::TYPES],
                'props' => ['type' => 'object'],
                'children' => ['type' => 'array'],
                'locks' => NodeSchemas::locks(),
                'metadata' => NodeSchemas::metadata(),
            ],
        ];
    }

    public static function props(string $type): array
    {
        $specific = match ($type) {
            'header' => [
                'variant' => ['enum' => ['simple', 'with_cta', 'centered']],
                'sticky' => ['type' => 'boolean'],
            ],
            'hero' => [
                'variant' => ['enum' => ['centered', 'split_left_image', 'split_right_image', 'background_image']],
                'image_url' => ['type' => ['string', 'null']],
            ],
            'feature_grid', 'stats_band' => [
                'columns' => ['enum' => [2, 3, 4]],
            ],
            'feature_split' => [
                'image_side' => ['enum' => ['left', 'right']],
                'image_url' => ['type' => 'string', 'minLength' => 1],
            ],
            'testimonial_grid' => [
                'columns' => ['enum' => [1, 2, 3]],
            ],
            'faq' => [
                'layout' => ['enum' => ['single_column', 'two_column']],
            ],
            'cta_band' => [
                'variant' => ['enum' => ['centered', 'split']],
            ],
            'contact_form' => [
                'submit_endpoint' => ['type' => ['string', 'null']],
            ],
            'footer' => [
                'variant' => ['enum' => ['simple', 'columned']],
                'columns' => ['enum' => [1, 2, 3, 4]],
            ],
            default => [],
        };

        $common = [
            'background' => ['enum' => ['default', 'neutral', 'inverse', 'accent', 'muted']],
            'padding' => ['enum' => ['sm', 'md', 'lg', 'xl']],
            'max_width' => ['enum' => ['narrow', 'default', 'wide', 'full']],
            'alignment' => ['enum' => ['left', 'center']],
        ];

        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => array_keys($common + $specific),
            'properties' => $common + $specific,
        ];
    }
}
