<?php

namespace App\Services\Llm;

class StructuredRequest
{
    public function __construct(
        public readonly string $stage,
        public readonly string $model,
        public readonly string $systemPrompt,
        public readonly string $userPrompt,
        public readonly string $toolName,
        public readonly array $schema,
        public readonly array $context = [],
        public readonly int $maxTokens = 4096,
        public readonly float $temperature = 0.2,
    ) {}

    public function toolDefinition(): array
    {
        return [
            'name' => $this->toolName,
            'description' => "Return the structured {$this->stage} result.",
            'input_schema' => $this->schema,
        ];
    }
}
