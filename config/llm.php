<?php

return [
    'default_provider' => env('LLM_PROVIDER', 'anthropic'),
    'model_cache_ttl_seconds' => (int) env('LLM_MODEL_CACHE_TTL_SECONDS', 86400),

    'providers' => [
        'anthropic' => [
            'label' => 'Anthropic',
            'driver' => 'anthropic',
            'implemented' => true,
            'api_key' => env('ANTHROPIC_API_KEY'),
            'models_refreshed_at' => '2026-05-21',
            'models' => [
                'planner' => env('ANTHROPIC_PLANNER_MODEL', 'claude-sonnet-4-6'),
                'section_generator' => env('ANTHROPIC_SECTION_MODEL', 'claude-sonnet-4-6'),
                'repair' => env('ANTHROPIC_REPAIR_MODEL', 'claude-sonnet-4-6'),
                'targeted_edit' => env('ANTHROPIC_EDIT_MODEL', 'claude-sonnet-4-6'),
            ],
            'available_models' => [
                [
                    'id' => 'claude-sonnet-4-6',
                    'label' => 'Claude Sonnet 4.6',
                ],
                [
                    'id' => 'claude-opus-4-7',
                    'label' => 'Claude Opus 4.7',
                ],
                [
                    'id' => 'claude-opus-4-6',
                    'label' => 'Claude Opus 4.6',
                ],
                [
                    'id' => 'claude-opus-4-5-20251101',
                    'label' => 'Claude Opus 4.5',
                ],
                [
                    'id' => 'claude-haiku-4-5-20251001',
                    'label' => 'Claude Haiku 4.5',
                ],
                [
                    'id' => 'claude-sonnet-4-5-20250929',
                    'label' => 'Claude Sonnet 4.5',
                ],
                [
                    'id' => 'claude-sonnet-4-20250514',
                    'label' => 'Claude Sonnet 4',
                ],
                [
                    'id' => 'claude-opus-4-1-20250805',
                    'label' => 'Claude Opus 4.1',
                ],
                [
                    'id' => 'claude-opus-4-20250514',
                    'label' => 'Claude Opus 4',
                ],
            ],
            'request_timeout' => (float) env('ANTHROPIC_REQUEST_TIMEOUT', 600),
            'section_max_tokens' => (int) env('ANTHROPIC_SECTION_MAX_TOKENS', 16000),
            'marker_max_tokens' => (int) env('ANTHROPIC_MARKER_MAX_TOKENS', 16000),
            'edit_max_tokens' => env('ANTHROPIC_EDIT_MAX_TOKENS', 8000),
        ],
    ],
];
