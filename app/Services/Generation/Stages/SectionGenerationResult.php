<?php

namespace App\Services\Generation\Stages;

class SectionGenerationResult
{
    public function __construct(
        public readonly string $html,
        public readonly ?string $recovery = null,
        public readonly array $llm = [],
    ) {}
}
