<?php

namespace App\Services\Llm;

class StructuredResponse
{
    public function __construct(
        public readonly string $stage,
        public readonly string $model,
        public readonly array $output,
        public readonly array $raw = [],
        public readonly array $usage = [],
    ) {}
}
