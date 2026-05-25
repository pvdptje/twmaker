<?php

namespace App\Services\Llm;

class TextRequest
{
    /**
     * @param  array<int, array{base64: string, mime_type: string}>  $images
     */
    public function __construct(
        public readonly string $stage,
        public readonly string $provider,
        public readonly string $model,
        public readonly string $systemPrompt,
        public readonly string $userPrompt,
        public readonly array $context = [],
        public readonly int $maxTokens = 4096,
        public readonly float $temperature = 0.2,
        public readonly ?string $apiKey = null,
        public readonly array $images = [],
    ) {}
}
