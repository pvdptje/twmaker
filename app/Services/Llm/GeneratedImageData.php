<?php

namespace App\Services\Llm;

readonly class GeneratedImageData
{
    public function __construct(
        public string $bytes,
        public string $mimeType,
        public ?string $revisedPrompt = null,
    ) {}
}
