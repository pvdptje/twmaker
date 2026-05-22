<?php

namespace App\Services\Llm;

use InvalidArgumentException;

class LlmManager implements LlmProvider
{
    public function sendStructured(StructuredRequest $request): StructuredResponse
    {
        return $this->driver($request->provider)->sendStructured($request);
    }

    public function sendStructuredStream(StructuredRequest $request, callable $onPartialJson): StructuredResponse
    {
        $driver = $this->driver($request->provider);

        if (! method_exists($driver, 'sendStructuredStream')) {
            return $driver->sendStructured($request);
        }

        return $driver->sendStructuredStream($request, $onPartialJson);
    }

    public function sendTextStream(StructuredRequest $request, callable $onDelta): StructuredResponse
    {
        $driver = $this->driver($request->provider);

        if (! method_exists($driver, 'sendTextStream')) {
            return $driver->sendStructured($request);
        }

        return $driver->sendTextStream($request, $onDelta);
    }

    private function driver(string $provider): LlmProvider
    {
        if (! (bool) config("llm.providers.{$provider}.implemented", false)) {
            throw new InvalidArgumentException("LLM provider [{$provider}] is not implemented.");
        }

        $driver = config("llm.providers.{$provider}.driver");

        return match ($driver) {
            'anthropic' => app(AnthropicProvider::class),
            'deepseek' => app(DeepSeekProvider::class),
            default => throw new InvalidArgumentException("LLM provider [{$provider}] is not implemented."),
        };
    }
}
