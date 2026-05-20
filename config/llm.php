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
        ],
    ],
];
