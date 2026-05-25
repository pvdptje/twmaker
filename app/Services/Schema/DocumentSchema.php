<?php

namespace App\Services\Schema;

class DocumentSchema
{
    public static function schema(): array
    {
        return [
            '$schema' => 'https://json-schema.org/draft/2020-12/schema',
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['schema_version', 'page_metadata', 'design_system', 'document_tree', 'generation_history'],
            'properties' => [
                'schema_version' => ['const' => 1],
                'page_metadata' => self::pageMetadata(),
                'design_system' => self::designSystem(),
                'document_tree' => [
                    'type' => 'array',
                    'items' => SectionSchemas::section(),
                ],
                'generation_history' => [
                    'type' => 'array',
                    'maxItems' => 500,
                    'items' => self::generationHistoryEntry(),
                ],
            ],
        ];
    }

    public static function pageMetadata(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['title', 'page_type', 'goal', 'audience', 'prompt_summary', 'status', 'created_at', 'updated_at'],
            'properties' => [
                'title' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 120],
                'page_type' => ['enum' => ['landing', 'pricing', 'about', 'product', 'contact', 'feature', 'generic']],
                'goal' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 500],
                'audience' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 300],
                'prompt_summary' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 2000],
                'status' => ['enum' => ['draft', 'generating', 'valid', 'invalid', 'error']],
                'created_at' => ['type' => 'string', 'format' => 'date-time'],
                'updated_at' => ['type' => 'string', 'format' => 'date-time'],
            ],
        ];
    }

    public static function designSystem(): array
    {
        $tailwindColors = ['slate', 'gray', 'zinc', 'neutral', 'stone', 'red', 'orange', 'amber', 'yellow', 'lime', 'green', 'emerald', 'teal', 'cyan', 'sky', 'blue', 'indigo', 'violet', 'purple', 'fuchsia', 'pink', 'rose'];

        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['colors', 'typography', 'spacing', 'radius', 'tone', 'dark_mode'],
            'properties' => [
                'colors' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['primary', 'accent', 'neutral', 'background', 'foreground'],
                    'properties' => [
                        'primary' => ['enum' => $tailwindColors],
                        'accent' => ['enum' => $tailwindColors],
                        'neutral' => ['enum' => $tailwindColors],
                        'background' => ['enum' => ['white', 'neutral-50', 'neutral-100', 'neutral-900', 'neutral-950']],
                        'foreground' => ['enum' => ['neutral-900', 'neutral-950', 'neutral-50', 'white']],
                    ],
                ],
                'typography' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['heading_family', 'body_family', 'scale'],
                    'properties' => [
                        'heading_family' => ['enum' => ['sans', 'serif', 'mono']],
                        'body_family' => ['enum' => ['sans', 'serif', 'mono']],
                        'scale' => ['enum' => ['compact', 'comfortable', 'generous']],
                    ],
                ],
                'spacing' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['density', 'section_padding'],
                    'properties' => [
                        'density' => ['enum' => ['compact', 'comfortable', 'generous']],
                        'section_padding' => ['enum' => ['sm', 'md', 'lg', 'xl']],
                    ],
                ],
                'radius' => ['enum' => ['none', 'sm', 'md', 'lg', 'xl', '2xl', 'full']],
                'tone' => ['enum' => ['professional', 'playful', 'technical', 'editorial', 'bold', 'minimal']],
                'dark_mode' => ['const' => false],
            ],
        ];
    }

    public static function generationHistoryEntry(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['id', 'occurred_at', 'kind', 'stage', 'target_id', 'summary', 'payload', 'level'],
            'properties' => [
                'id' => ['type' => 'string', 'pattern' => '^evt_[0-9a-hjkmnp-tv-z]{26}$', 'maxLength' => 32],
                'occurred_at' => ['type' => 'string', 'format' => 'date-time'],
                'kind' => ['enum' => ['planner_started', 'planner_proposed_structure', 'planner_finished', 'section_generation_started', 'section_generation_partial', 'section_generation_finished', 'element_resolution_started', 'element_resolution_finished', 'assembly_finished', 'validation_failed', 'validation_succeeded', 'repair_attempt', 'repair_exhausted', 'render_succeeded', 'render_failed', 'edit_requested', 'edit_applied', 'edit_rejected', 'insert_requested', 'insert_applied', 'insert_rejected', 'remove_requested', 'remove_applied', 'remove_rejected', 'granularize_requested', 'granularize_applied', 'granularize_rejected']],
                'stage' => ['enum' => ['planner', 'section_generation', 'element_resolution', 'assembly', 'validation', 'repair', 'render', 'targeted_edit', 'section_inserter', 'section_remover']],
                'target_id' => ['type' => ['string', 'null'], 'maxLength' => 32],
                'summary' => ['type' => 'string', 'maxLength' => 200],
                'payload' => ['type' => 'object'],
                'level' => ['enum' => ['info', 'warning', 'error', 'success']],
            ],
        ];
    }
}
