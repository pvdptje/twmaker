<?php

return [
    /*
    |--------------------------------------------------------------------------
    | LLM Model Pricing
    |--------------------------------------------------------------------------
    |
    | Prices are in USD per one million tokens. Providers generally do not
    | expose a stable pricing API, so update these values manually when needed.
    | Unknown provider/model pairs continue to show token usage without cost.
    | OpenAI prices below use Standard short-context API pricing and do not
    | include Batch, Flex, Priority, long-context, or regional processing uplifts.
    |
    | Example:
    |
    | 'models' => [
    |     'anthropic' => [
    |         'claude-sonnet-4-20250514' => [
    |             'input' => 3.00,
    |             'output' => 15.00,
    |             'cache_write' => 3.75,
    |             'cache_read' => 0.30,
    |         ],
    |     ],
    | ],
    |
    */

    'currency' => 'USD',

    'unit_tokens' => 1_000_000,

    'models' => [
        'openai' => [
            'gpt-5.5' => [
                'input' => 5.00,
                'cache_read' => 0.50,
                'output' => 30.00,
            ],
            'gpt-5.4' => [
                'input' => 2.50,
                'cache_read' => 0.25,
                'output' => 15.00,
            ],
            'gpt-5.4-mini' => [
                'input' => 0.75,
                'cache_read' => 0.075,
                'output' => 4.50,
            ],
            'gpt-5.4-nano' => [
                'input' => 0.20,
                'cache_read' => 0.02,
                'output' => 1.25,
            ],
            'chat-latest' => [
                'input' => 5.00,
                'cache_read' => 0.50,
                'output' => 30.00,
            ],
            'gpt-5.3-codex' => [
                'input' => 1.75,
                'cache_read' => 0.175,
                'output' => 14.00,
            ],
        ],
    ],
];
