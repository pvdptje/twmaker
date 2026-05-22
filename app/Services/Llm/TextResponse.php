<?php

namespace App\Services\Llm;

class TextResponse
{
    public function __construct(
        public readonly string $stage,
        public readonly string $model,
        public readonly string $text,
        public readonly array $raw = [],
        public readonly array $usage = [],
    ) {}
}
