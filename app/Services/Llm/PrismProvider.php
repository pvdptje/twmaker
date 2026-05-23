<?php

namespace App\Services\Llm;

use Illuminate\Support\Facades\Log;
use Prism\Prism\Enums\StreamEventType;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Schema\RawSchema;
use Prism\Prism\Streaming\Events\StreamEndEvent;
use Prism\Prism\Streaming\Events\TextDeltaEvent;
use Throwable;

class PrismProvider implements LlmProvider
{
    public function sendStructured(StructuredRequest $request): StructuredResponse
    {
        $userContent = $this->buildUserContent($request);
        $startedAt = microtime(true);

        Log::info('Prism structured request started.', [
            'stage' => $request->stage,
            'provider' => $request->provider,
            'model' => $request->model,
            'max_tokens' => $request->maxTokens,
            'prompt_bytes' => strlen($userContent),
        ]);

        try {
            $response = Prism::structured()
                ->using($this->prismProvider($request->provider), $request->model, $this->providerConfig($request))
                ->withClientOptions($this->clientOptions($request))
                ->withSystemPrompt($request->systemPrompt)
                ->withPrompt($userContent)
                ->withSchema(new RawSchema($request->toolName, $request->schema))
                ->withMaxTokens($request->maxTokens)
                ->usingTemperature($request->temperature)
                ->withProviderOptions($this->structuredProviderOptions($request))
                ->asStructured();
        } catch (Throwable $exception) {
            if ($fallbackRequest = $this->fallbackRequestForRejectedModel($request, $exception)) {
                return $this->sendStructured($fallbackRequest);
            }

            Log::error('Prism structured request failed.', [
                'stage' => $request->stage,
                'provider' => $request->provider,
                'model' => $request->model,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        Log::info('Prism structured request completed.', [
            'stage' => $request->stage,
            'provider' => $request->provider,
            'model' => $request->model,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'finish_reason' => $response->finishReason->value,
        ]);

        return new StructuredResponse(
            stage: $request->stage,
            model: (string) ($response->meta->model ?: $request->model),
            output: $response->structured ?? [],
            raw: [
                'id' => $response->meta->id,
                'finish_reason' => $response->finishReason->value,
                'raw' => $response->raw,
            ],
            usage: $response->usage->toArray(),
        );
    }

    public function sendTextStream(TextRequest $request, callable $onDelta): TextResponse
    {
        if ($legacyProvider = $this->legacyStreamingProvider($request->provider)) {
            return $legacyProvider->sendTextStream($request, $onDelta);
        }

        $userContent = $this->buildUserContent($request);
        $startedAt = microtime(true);
        $text = '';
        $streamedText = '';
        $usage = [];
        $messageId = null;
        $finishReason = null;
        $prefixBuffer = '';
        $prefixResolved = false;

        Log::info('Prism text stream started.', [
            'stage' => $request->stage,
            'provider' => $request->provider,
            'model' => $request->model,
            'max_tokens' => $request->maxTokens,
            'prompt_bytes' => strlen($userContent),
        ]);

        try {
            $stream = Prism::text()
                ->using($this->prismProvider($request->provider), $request->model, $this->providerConfig($request))
                ->withClientOptions($this->clientOptions($request))
                ->withSystemPrompt($request->systemPrompt)
                ->withPrompt($userContent)
                ->withMaxTokens($request->maxTokens)
                ->usingTemperature($request->temperature)
                ->asStream();

            foreach ($stream as $event) {
                if ($event instanceof TextDeltaEvent || $event->type() === StreamEventType::TextDelta) {
                    $eventData = $event->toArray();
                    $chunk = $this->scrubText((string) ($eventData['delta'] ?? ''));
                    $messageId = (string) ($eventData['message_id'] ?? $messageId ?? '');

                    if ($chunk === '') {
                        continue;
                    }

                    $text .= $chunk;
                    $chunk = $this->stripStreamingCodeFencePrefix($chunk, $prefixBuffer, $prefixResolved);
                    $chunk = $this->stripStreamingCodeFenceSuffix($chunk);

                    if ($chunk === '') {
                        continue;
                    }

                    $position = strlen($streamedText);
                    $streamedText .= $chunk;
                    $onDelta($chunk, $position, $request);

                    continue;
                }

                if ($event instanceof StreamEndEvent || $event->type() === StreamEventType::StreamEnd) {
                    $eventData = $event->toArray();
                    $finishReason = (string) ($eventData['finish_reason'] ?? $finishReason ?? '');
                    $usage = is_array($eventData['usage'] ?? null) ? $eventData['usage'] : $usage;
                }
            }
        } catch (Throwable $exception) {
            if ($fallbackRequest = $this->fallbackRequestForRejectedModel($request, $exception)) {
                return $this->sendTextStream($fallbackRequest, $onDelta);
            }

            Log::error('Prism text stream failed.', [
                'stage' => $request->stage,
                'provider' => $request->provider,
                'model' => $request->model,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        Log::info('Prism text stream completed.', [
            'stage' => $request->stage,
            'provider' => $request->provider,
            'model' => $request->model,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'html_bytes' => strlen($text),
            'finish_reason' => $finishReason,
        ]);

        return new TextResponse(
            stage: $request->stage,
            model: $request->model,
            text: $this->stripCodeFence($this->scrubText($text)),
            raw: ['id' => $messageId ?: null, 'finish_reason' => $finishReason],
            usage: $this->scrubArray($usage),
        );
    }

    public function sendStructuredStream(StructuredRequest $request, callable $onPartialJson): StructuredResponse
    {
        if ($request->provider === 'anthropic') {
            return app(AnthropicProvider::class)->sendStructuredStream($request, $onPartialJson);
        }

        return $this->sendStructured($request);
    }

    private function prismProvider(string $provider): string
    {
        return (string) config("llm.providers.{$provider}.prism_provider", $provider);
    }

    private function legacyStreamingProvider(string $provider): AnthropicProvider|DeepSeekProvider|null
    {
        return match ($provider) {
            'anthropic' => app(AnthropicProvider::class),
            'deepseek' => app(DeepSeekProvider::class),
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function providerConfig(StructuredRequest|TextRequest $request): array
    {
        $config = [];
        $apiKey = trim((string) ($request->apiKey ?: config("llm.providers.{$request->provider}.api_key")));

        if ($apiKey !== '') {
            $config['api_key'] = $apiKey;
        }

        $url = $this->providerUrl($request->provider);
        if ($url !== null) {
            $config['url'] = $url;
        }

        return $config;
    }

    private function providerUrl(string $provider): ?string
    {
        $url = trim((string) config("llm.providers.{$provider}.base_url", ''));

        if ($url === '') {
            return null;
        }

        $url = rtrim($url, '/');

        if ($this->prismProvider($provider) === 'deepseek' && ! str_ends_with($url, '/v1')) {
            return $url.'/v1';
        }

        return $url;
    }

    /**
     * @return array<string, mixed>
     */
    private function clientOptions(StructuredRequest|TextRequest $request): array
    {
        return [
            'timeout' => (float) config("llm.providers.{$request->provider}.request_timeout", 600),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function structuredProviderOptions(StructuredRequest $request): array
    {
        if ($this->prismProvider($request->provider) !== 'anthropic') {
            return [];
        }

        return ['use_tool_calling' => true];
    }

    private function buildUserContent(StructuredRequest|TextRequest $request): string
    {
        if ($request instanceof TextRequest) {
            return trim($request->userPrompt."\n\nContext:\n".$this->contextText($request->context));
        }

        return trim($request->userPrompt."\n\nContext JSON:\n".json_encode(
            $request->context,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        ));
    }

    private function contextText(array $context): string
    {
        $lines = [];

        foreach ($context as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $lines[] = str_replace('_', ' ', (string) $key).': '.(string) $value;
            }
        }

        return $lines !== [] ? implode("\n", $lines) : 'none';
    }

    private function fallbackRequestForRejectedModel(StructuredRequest|TextRequest $request, Throwable $exception): StructuredRequest|TextRequest|null
    {
        if (! $this->isModelRejected($exception)) {
            return null;
        }

        app(ProviderModelCatalog::class)->forgetModel($request->provider, $request->apiKey, $request->model);

        $fallbackModel = app(LlmRegistry::class)->defaultModel($request->provider, $request->stage, $request->apiKey);

        if ($fallbackModel === '' || $fallbackModel === $request->model) {
            return null;
        }

        Log::warning('Prism model was rejected by provider. Retrying with fallback model.', [
            'stage' => $request->stage,
            'provider' => $request->provider,
            'model' => $request->model,
            'fallback_model' => $fallbackModel,
        ]);

        if ($request instanceof TextRequest) {
            return new TextRequest(
                stage: $request->stage,
                provider: $request->provider,
                model: $fallbackModel,
                systemPrompt: $request->systemPrompt,
                userPrompt: $request->userPrompt,
                context: $request->context,
                maxTokens: $request->maxTokens,
                temperature: $request->temperature,
                apiKey: $request->apiKey,
            );
        }

        return new StructuredRequest(
            stage: $request->stage,
            provider: $request->provider,
            model: $fallbackModel,
            systemPrompt: $request->systemPrompt,
            userPrompt: $request->userPrompt,
            toolName: $request->toolName,
            schema: $request->schema,
            context: $request->context,
            maxTokens: $request->maxTokens,
            temperature: $request->temperature,
            apiKey: $request->apiKey,
        );
    }

    private function isModelRejected(Throwable $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'model')
            && (
                str_contains($message, 'not_found')
                || str_contains($message, 'not found')
                || str_contains($message, 'does not exist')
                || str_contains($message, 'invalid model')
            );
    }

    private function stripCodeFence(string $text): string
    {
        $text = trim($text);

        if (preg_match('/^```(?:html)?\s*(.*?)\s*```$/is', $text, $matches)) {
            return trim($matches[1]);
        }

        $text = preg_replace('/^\s*```[A-Za-z0-9_-]*[ \t]*(?:\R|$)/', '', $text, 1) ?? $text;
        $text = preg_replace('/(?:\R|^)```[ \t]*$/', '', $text, 1) ?? $text;

        return trim($text);
    }

    private function stripStreamingCodeFencePrefix(string $chunk, string &$prefixBuffer, bool &$prefixResolved): string
    {
        if ($prefixResolved) {
            return $chunk;
        }

        $prefixBuffer .= $chunk;

        if (preg_match('/^\s*```[A-Za-z0-9_-]*[ \t]*(?:\R|$)/', $prefixBuffer, $matches)) {
            $prefixResolved = true;
            $chunk = substr($prefixBuffer, strlen($matches[0]));
            $prefixBuffer = '';

            return $chunk;
        }

        if (strlen($prefixBuffer) < 32 && preg_match('/^\s*`{0,3}[A-Za-z0-9_-]*[ \t]*$/', $prefixBuffer)) {
            return '';
        }

        $prefixResolved = true;
        $chunk = $prefixBuffer;
        $prefixBuffer = '';

        return $chunk;
    }

    private function stripStreamingCodeFenceSuffix(string $chunk): string
    {
        return preg_replace('/(?:\R|^)```[ \t]*$/', '', $chunk, 1) ?? $chunk;
    }

    private function scrubText(string $value): string
    {
        if (mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        return mb_scrub($value, 'UTF-8');
    }

    private function scrubArray(array $value): array
    {
        foreach ($value as $key => $item) {
            $value[$key] = match (true) {
                is_string($item) => $this->scrubText($item),
                is_array($item) => $this->scrubArray($item),
                default => $item,
            };
        }

        return $value;
    }
}
