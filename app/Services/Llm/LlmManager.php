<?php

namespace App\Services\Llm;

use InvalidArgumentException;

class LlmManager implements LlmProvider
{
    public function sendStructured(StructuredRequest $request): StructuredResponse
    {
        return $this->driver($request->provider)->sendStructured($request);
    }

    private function driver(string $provider): LlmProvider
    {
        if (! (bool) config("llm.providers.{$provider}.implemented", false)) {
            throw new InvalidArgumentException("LLM provider [{$provider}] is not implemented.");
        }

        $driver = config("llm.providers.{$provider}.driver");

        return match ($driver) {
            'anthropic' => app(AnthropicProvider::class),
            default => throw new InvalidArgumentException("LLM provider [{$provider}] is not implemented."),
        };
    }
}
