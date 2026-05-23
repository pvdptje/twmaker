<?php

$textDefaults = [
    'section_max_tokens' => (int) env('LLM_SECTION_MAX_TOKENS', 32000),
    'marker_max_tokens' => (int) env('LLM_MARKER_MAX_TOKENS', 8000),
    'edit_max_tokens' => (int) env('LLM_EDIT_MAX_TOKENS', 8000),
    'request_timeout' => (float) env('LLM_REQUEST_TIMEOUT', env('PRISM_REQUEST_TIMEOUT', 600)),
];

return [
    'default_provider' => env('LLM_PROVIDER', 'anthropic'),
    'model_cache_ttl_seconds' => (int) env('LLM_MODEL_CACHE_TTL_SECONDS', 86400),

    'providers' => [
        'anthropic' => array_merge($textDefaults, [
            'label' => 'Anthropic',
            'driver' => 'prism',
            'prism_provider' => 'anthropic',
            'implemented' => true,
            'api_key' => env('ANTHROPIC_API_KEY'),
            'url' => env('ANTHROPIC_URL', 'https://api.anthropic.com/v1'),
            'models_refreshed_at' => 'provider API',
            'models' => [
                'section_generator' => env('ANTHROPIC_SECTION_MODEL', env('LLM_MODEL', '')),
                'html_marker' => env('ANTHROPIC_MARKER_MODEL', env('LLM_MODEL', '')),
                'targeted_edit' => env('ANTHROPIC_EDIT_MODEL', env('LLM_MODEL', '')),
            ],
        ]),

        'deepseek' => array_merge($textDefaults, [
            'label' => 'DeepSeek',
            'driver' => 'prism',
            'prism_provider' => 'deepseek',
            'implemented' => true,
            'api_key' => env('DEEPSEEK_API_KEY'),
            'url' => env('DEEPSEEK_URL', env('DEEPSEEK_BASE_URL', 'https://api.deepseek.com/v1')),
            'models_refreshed_at' => 'provider API',
            'models' => [
                'section_generator' => env('DEEPSEEK_SECTION_MODEL', env('LLM_MODEL', '')),
                'html_marker' => env('DEEPSEEK_MARKER_MODEL', env('LLM_MODEL', '')),
                'targeted_edit' => env('DEEPSEEK_EDIT_MODEL', env('LLM_MODEL', '')),
            ],
        ]),

        'openai' => array_merge($textDefaults, [
            'label' => 'OpenAI',
            'driver' => 'prism',
            'prism_provider' => 'openai',
            'implemented' => true,
            'api_key' => env('OPENAI_API_KEY'),
            'url' => env('OPENAI_URL', 'https://api.openai.com/v1'),
            'models_refreshed_at' => 'provider API',
            'models' => [
                'section_generator' => env('OPENAI_SECTION_MODEL', env('LLM_MODEL', '')),
                'html_marker' => env('OPENAI_MARKER_MODEL', env('LLM_MODEL', '')),
                'targeted_edit' => env('OPENAI_EDIT_MODEL', env('LLM_MODEL', '')),
            ],
        ]),

        'openrouter' => array_merge($textDefaults, [
            'label' => 'OpenRouter',
            'driver' => 'prism',
            'prism_provider' => 'openrouter',
            'implemented' => true,
            'api_key' => env('OPENROUTER_API_KEY'),
            'url' => env('OPENROUTER_URL', 'https://openrouter.ai/api/v1'),
            'models_refreshed_at' => 'provider API',
            'models' => [
                'section_generator' => env('OPENROUTER_SECTION_MODEL', env('LLM_MODEL', '')),
                'html_marker' => env('OPENROUTER_MARKER_MODEL', env('LLM_MODEL', '')),
                'targeted_edit' => env('OPENROUTER_EDIT_MODEL', env('LLM_MODEL', '')),
            ],
        ]),

        'ollama' => array_merge($textDefaults, [
            'label' => 'Ollama',
            'driver' => 'prism',
            'prism_provider' => 'ollama',
            'implemented' => true,
            'requires_api_key' => false,
            'url' => env('OLLAMA_URL', 'http://localhost:11434'),
            'models_refreshed_at' => 'local API',
            'models' => [
                'section_generator' => env('OLLAMA_SECTION_MODEL', env('LLM_MODEL', '')),
                'html_marker' => env('OLLAMA_MARKER_MODEL', env('LLM_MODEL', '')),
                'targeted_edit' => env('OLLAMA_EDIT_MODEL', env('LLM_MODEL', '')),
            ],
        ]),

        'mistral' => array_merge($textDefaults, [
            'label' => 'Mistral',
            'driver' => 'prism',
            'prism_provider' => 'mistral',
            'implemented' => true,
            'api_key' => env('MISTRAL_API_KEY'),
            'url' => env('MISTRAL_URL', 'https://api.mistral.ai/v1'),
            'models_refreshed_at' => 'provider API',
            'models' => [
                'section_generator' => env('MISTRAL_SECTION_MODEL', env('LLM_MODEL', '')),
                'html_marker' => env('MISTRAL_MARKER_MODEL', env('LLM_MODEL', '')),
                'targeted_edit' => env('MISTRAL_EDIT_MODEL', env('LLM_MODEL', '')),
            ],
        ]),

        'groq' => array_merge($textDefaults, [
            'label' => 'Groq',
            'driver' => 'prism',
            'prism_provider' => 'groq',
            'implemented' => true,
            'api_key' => env('GROQ_API_KEY'),
            'url' => env('GROQ_URL', 'https://api.groq.com/openai/v1'),
            'models_refreshed_at' => 'provider API',
            'models' => [
                'section_generator' => env('GROQ_SECTION_MODEL', env('LLM_MODEL', '')),
                'html_marker' => env('GROQ_MARKER_MODEL', env('LLM_MODEL', '')),
                'targeted_edit' => env('GROQ_EDIT_MODEL', env('LLM_MODEL', '')),
            ],
        ]),

        'xai' => array_merge($textDefaults, [
            'label' => 'xAI',
            'driver' => 'prism',
            'prism_provider' => 'xai',
            'implemented' => true,
            'api_key' => env('XAI_API_KEY'),
            'url' => env('XAI_URL', 'https://api.x.ai/v1'),
            'models_refreshed_at' => 'provider API',
            'models' => [
                'section_generator' => env('XAI_SECTION_MODEL', env('LLM_MODEL', '')),
                'html_marker' => env('XAI_MARKER_MODEL', env('LLM_MODEL', '')),
                'targeted_edit' => env('XAI_EDIT_MODEL', env('LLM_MODEL', '')),
            ],
        ]),

        'gemini' => array_merge($textDefaults, [
            'label' => 'Gemini',
            'driver' => 'prism',
            'prism_provider' => 'gemini',
            'implemented' => true,
            'api_key' => env('GEMINI_API_KEY'),
            'url' => env('GEMINI_URL', 'https://generativelanguage.googleapis.com/v1beta/models'),
            'models_refreshed_at' => 'provider API',
            'models' => [
                'section_generator' => env('GEMINI_SECTION_MODEL', env('LLM_MODEL', '')),
                'html_marker' => env('GEMINI_MARKER_MODEL', env('LLM_MODEL', '')),
                'targeted_edit' => env('GEMINI_EDIT_MODEL', env('LLM_MODEL', '')),
            ],
        ]),

        'perplexity' => array_merge($textDefaults, [
            'label' => 'Perplexity',
            'driver' => 'prism',
            'prism_provider' => 'perplexity',
            'implemented' => true,
            'api_key' => env('PERPLEXITY_API_KEY'),
            'url' => env('PERPLEXITY_URL', 'https://api.perplexity.ai'),
            'models_refreshed_at' => null,
            'models' => [
                'section_generator' => env('PERPLEXITY_SECTION_MODEL', env('LLM_MODEL', '')),
                'html_marker' => env('PERPLEXITY_MARKER_MODEL', env('LLM_MODEL', '')),
                'targeted_edit' => env('PERPLEXITY_EDIT_MODEL', env('LLM_MODEL', '')),
            ],
        ]),

        'z' => array_merge($textDefaults, [
            'label' => 'Z.ai',
            'driver' => 'prism',
            'prism_provider' => 'z',
            'implemented' => true,
            'api_key' => env('Z_API_KEY'),
            'url' => env('Z_URL', 'https://api.z.ai/api/paas/v4'),
            'models_refreshed_at' => null,
            'models' => [
                'section_generator' => env('Z_SECTION_MODEL', env('LLM_MODEL', '')),
                'html_marker' => env('Z_MARKER_MODEL', env('LLM_MODEL', '')),
                'targeted_edit' => env('Z_EDIT_MODEL', env('LLM_MODEL', '')),
            ],
        ]),
    ],
];
