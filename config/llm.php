<?php

return [
    'default_provider' => env('LLM_PROVIDER', 'anthropic'),

    'providers' => [
        'anthropic' => [
            'api_key' => env('ANTHROPIC_API_KEY'),
            'models' => [
                'planner' => env('ANTHROPIC_PLANNER_MODEL', 'claude-sonnet-4-5'),
                'section_generator' => env('ANTHROPIC_SECTION_MODEL', 'claude-sonnet-4-5'),
                'repair' => env('ANTHROPIC_REPAIR_MODEL', 'claude-sonnet-4-5'),
                'targeted_edit' => env('ANTHROPIC_EDIT_MODEL', 'claude-sonnet-4-5'),
            ],
            'request_timeout' => (float) env('ANTHROPIC_REQUEST_TIMEOUT', 600),
            'section_max_tokens' => (int) env('ANTHROPIC_SECTION_MAX_TOKENS', 16000),
            'marker_max_tokens' => (int) env('ANTHROPIC_MARKER_MAX_TOKENS', 16000),
            'edit_max_tokens' => env('ANTHROPIC_EDIT_MAX_TOKENS', 8000),
        ],
    ],
];
