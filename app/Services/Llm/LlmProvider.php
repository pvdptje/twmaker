<?php

namespace App\Services\Llm;

interface LlmProvider
{
    public function sendStructured(StructuredRequest $request): StructuredResponse;
}
