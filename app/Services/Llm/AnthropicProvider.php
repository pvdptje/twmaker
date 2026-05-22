<?php

namespace App\Services\Llm;

use Anthropic\Client;
use Anthropic\Messages\InputJSONDelta;
use Anthropic\Messages\RawContentBlockDeltaEvent;
use Anthropic\Messages\RawContentBlockStartEvent;
use Anthropic\Messages\RawMessageDeltaEvent;
use Anthropic\Messages\RawMessageStartEvent;
use Anthropic\Messages\TextDelta;
use Anthropic\RequestOptions;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class AnthropicProvider implements LlmProvider
{
    public function __construct(private readonly ?Client $client = null) {}

    public function sendStructured(StructuredRequest $request): StructuredResponse
    {
        $client = $this->client($request);

        $userContent = $this->buildUserContent($request);
        $startedAt = microtime(true);

        Log::info('LLM structured request started.', [
            'stage' => $request->stage,
            'model' => $request->model,
            'max_tokens' => $request->maxTokens,
            'prompt_bytes' => strlen($userContent),
        ]);

        try {
            $message = $client->messages->create(
                maxTokens: $request->maxTokens,
                messages: [[
                    'role' => 'user',
                    'content' => $userContent,
                ]],
                model: $request->model,
                system: $request->systemPrompt,
                temperature: $request->temperature,
                toolChoice: ['type' => 'tool', 'name' => $request->toolName],
                tools: [$request->toolDefinition()],
            );
        } catch (Throwable $exception) {
            if ($this->isModelNotFound($exception)) {
                app(ProviderModelCatalog::class)->forgetModel($request->provider, $request->apiKey, $request->model);

                $fallbackRequest = $this->fallbackRequest($request);

                if ($fallbackRequest !== null) {
                    Log::warning('LLM model was rejected by provider. Retrying with fallback model.', [
                        'stage' => $request->stage,
                        'provider' => $request->provider,
                        'model' => $request->model,
                        'fallback_model' => $fallbackRequest->model,
                    ]);

                    return $this->sendStructured($fallbackRequest);
                }
            }

            Log::error('LLM structured request failed.', [
                'stage' => $request->stage,
                'model' => $request->model,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        Log::info('LLM structured request completed.', [
            'stage' => $request->stage,
            'model' => $request->model,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'stop_reason' => $message->stopReason ?? null,
        ]);

        $output = $this->extractToolInput($message->content ?? [], $request->toolName);

        return new StructuredResponse(
            stage: $request->stage,
            model: $request->model,
            output: $output,
            raw: ['id' => $message->id ?? null, 'stop_reason' => $message->stopReason ?? null],
            usage: (array) ($message->usage ?? []),
        );
    }

    public function sendStructuredStream(StructuredRequest $request, callable $onPartialJson): StructuredResponse
    {
        $client = $this->client($request);
        $userContent = $this->buildUserContent($request);
        $startedAt = microtime(true);
        $toolBlockIndexes = [];
        $partialJsonByIndex = [];
        $messageId = null;
        $usage = [];

        Log::info('LLM structured stream started.', [
            'stage' => $request->stage,
            'model' => $request->model,
            'max_tokens' => $request->maxTokens,
            'prompt_bytes' => strlen($userContent),
        ]);

        try {
            $stream = $client->messages->createStream(
                maxTokens: $request->maxTokens,
                messages: [[
                    'role' => 'user',
                    'content' => $userContent,
                ]],
                model: $request->model,
                system: $request->systemPrompt,
                temperature: $request->temperature,
                toolChoice: ['type' => 'tool', 'name' => $request->toolName],
                tools: [$request->toolDefinition()],
            );

            foreach ($stream as $event) {
                if ($event instanceof RawMessageStartEvent) {
                    $messageId = $event->message->id ?? null;
                    $usage = $this->mergeUsage($usage, (array) ($event->message->usage ?? []));

                    continue;
                }

                if ($event instanceof RawContentBlockStartEvent) {
                    $block = $event->contentBlock;
                    $type = $block->type ?? null;
                    $name = $block->name ?? null;

                    if ($type === 'tool_use' && $name === $request->toolName) {
                        $toolBlockIndexes[$event->index] = true;
                        $partialJsonByIndex[$event->index] = '';
                    }

                    continue;
                }

                if ($event instanceof RawContentBlockDeltaEvent && isset($toolBlockIndexes[$event->index])) {
                    $delta = $event->delta;

                    if ($delta instanceof InputJSONDelta) {
                        $partialJsonByIndex[$event->index] .= $delta->partialJSON;
                        $onPartialJson($partialJsonByIndex[$event->index], $delta->partialJSON, $request);
                    }

                    continue;
                }

                if ($event instanceof RawMessageDeltaEvent) {
                    $usage = $this->mergeUsage($usage, (array) ($event->usage ?? []));
                }
            }
        } catch (Throwable $exception) {
            if ($this->isModelNotFound($exception)) {
                app(ProviderModelCatalog::class)->forgetModel($request->provider, $request->apiKey, $request->model);

                $fallbackRequest = $this->fallbackRequest($request);

                if ($fallbackRequest !== null) {
                    Log::warning('LLM streaming model was rejected by provider. Retrying with fallback model.', [
                        'stage' => $request->stage,
                        'provider' => $request->provider,
                        'model' => $request->model,
                        'fallback_model' => $fallbackRequest->model,
                    ]);

                    return $this->sendStructuredStream($fallbackRequest, $onPartialJson);
                }
            }

            Log::error('LLM structured stream failed.', [
                'stage' => $request->stage,
                'model' => $request->model,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        Log::info('LLM structured stream completed.', [
            'stage' => $request->stage,
            'model' => $request->model,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);

        $partialJson = $this->firstNonEmpty($partialJsonByIndex);
        if ($partialJson === null) {
            throw new RuntimeException("Anthropic stream did not include expected tool_use [{$request->toolName}].");
        }

        $output = $this->decodeStructuredStreamOutput($partialJson);

        return new StructuredResponse(
            stage: $request->stage,
            model: $request->model,
            output: $output,
            raw: ['id' => $messageId],
            usage: $usage,
        );
    }

    public function sendTextStream(TextRequest $request, callable $onDelta): TextResponse
    {
        $client = $this->client($request);
        $userContent = $this->buildUserContent($request);
        $startedAt = microtime(true);
        $messageId = null;
        $usage = [];
        $text = '';
        $stopReason = null;
        $debugLogPath = $this->startLlmOutputLog($request);

        Log::info('LLM text stream started.', [
            'stage' => $request->stage,
            'model' => $request->model,
            'max_tokens' => $request->maxTokens,
            'prompt_bytes' => strlen($userContent),
            'output_log' => $debugLogPath,
        ]);

        try {
            $stream = $client->messages->createStream(
                maxTokens: $request->maxTokens,
                messages: [[
                    'role' => 'user',
                    'content' => $userContent,
                ]],
                model: $request->model,
                system: $request->systemPrompt,
                temperature: $request->temperature,
            );

            foreach ($stream as $event) {
                if ($event instanceof RawMessageStartEvent) {
                    $messageId = $event->message->id ?? null;
                    $usage = $this->mergeUsage($usage, (array) ($event->message->usage ?? []));

                    continue;
                }

                if ($event instanceof RawContentBlockDeltaEvent && $event->delta instanceof TextDelta) {
                    $chunk = $event->delta->text;
                    $position = strlen($text);
                    $text .= $chunk;
                    $this->appendLlmOutputLog($debugLogPath, $chunk);
                    $onDelta($chunk, $position, $request);

                    continue;
                }

                if ($event instanceof RawMessageDeltaEvent) {
                    $usage = $this->mergeUsage($usage, (array) ($event->usage ?? []));
                    $stopReason = $event->delta->stopReason ?? $stopReason;
                }
            }
        } catch (Throwable $exception) {
            if ($this->isModelNotFound($exception)) {
                app(ProviderModelCatalog::class)->forgetModel($request->provider, $request->apiKey, $request->model);

                $fallbackRequest = $this->fallbackRequest($request);

                if ($fallbackRequest !== null) {
                    Log::warning('LLM text streaming model was rejected by provider. Retrying with fallback model.', [
                        'stage' => $request->stage,
                        'provider' => $request->provider,
                        'model' => $request->model,
                        'fallback_model' => $fallbackRequest->model,
                    ]);

                    return $this->sendTextStream($fallbackRequest, $onDelta);
                }
            }

            Log::error('LLM text stream failed.', [
                'stage' => $request->stage,
                'model' => $request->model,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
                'output_log' => $debugLogPath,
            ]);

            throw $exception;
        }

        $this->finishLlmOutputLog($debugLogPath, $request, $text, $usage, $messageId, $stopReason);

        Log::info('LLM text stream completed.', [
            'stage' => $request->stage,
            'model' => $request->model,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'html_bytes' => strlen($text),
            'stop_reason' => $stopReason,
            'output_log' => $debugLogPath,
        ]);

        return new TextResponse(
            stage: $request->stage,
            model: $request->model,
            text: $this->stripCodeFence($text),
            raw: ['id' => $messageId, 'stop_reason' => $stopReason],
            usage: $usage,
        );
    }

    private function client(StructuredRequest|TextRequest $request): Client
    {
        return $this->client ?? new Client(
            apiKey: $request->apiKey ?: (string) config("llm.providers.{$request->provider}.api_key"),
            requestOptions: RequestOptions::with(
                timeout: (float) config("llm.providers.{$request->provider}.request_timeout", 600),
            ),
        );
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

    private function extractToolInput(array $content, string $toolName): array
    {
        foreach ($content as $block) {
            $type = $block->type ?? ($block['type'] ?? null);
            $name = $block->name ?? ($block['name'] ?? null);

            if ($type === 'tool_use' && $name === $toolName) {
                $input = $block->input ?? ($block['input'] ?? []);

                return json_decode(json_encode($input, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
            }
        }

        throw new RuntimeException("Anthropic response did not include expected tool_use [{$toolName}].");
    }

    private function firstNonEmpty(array $values): ?string
    {
        foreach ($values as $value) {
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function decodeStructuredStreamOutput(string $partialJson): array
    {
        try {
            $decoded = json_decode($partialJson, true, 512, JSON_THROW_ON_ERROR);

            return is_array($decoded) ? $decoded : [];
        } catch (Throwable) {
            $rawHtml = $this->partialJsonStringValue($partialJson, 'raw_html')
                ?? $this->partialJsonStringValue($partialJson, 'html_source');

            if (is_string($rawHtml) && trim($rawHtml) !== '') {
                return ['raw_html' => $rawHtml];
            }

            throw new RuntimeException('Anthropic stream ended before usable structured output was available.');
        }
    }

    private function partialJsonStringValue(string $json, string $field): ?string
    {
        if (! preg_match('/"'.preg_quote($field, '/').'"\s*:\s*"/', $json, $matches, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $offset = $matches[0][1] + strlen($matches[0][0]);
        $value = '';
        $length = strlen($json);

        for ($i = $offset; $i < $length; $i++) {
            $char = $json[$i];

            if ($char === '"') {
                break;
            }

            if ($char !== '\\') {
                $value .= $char;

                continue;
            }

            if ($i + 1 >= $length) {
                break;
            }

            $next = $json[++$i];
            $value .= match ($next) {
                '"', '\\', '/' => $next,
                'b' => "\b",
                'f' => "\f",
                'n' => "\n",
                'r' => "\r",
                't' => "\t",
                'u' => $this->decodeUnicodeEscape(substr($json, $i + 1, 4), $i),
                default => $next,
            };
        }

        return $value;
    }

    private function decodeUnicodeEscape(string $hex, int &$index): string
    {
        if (! preg_match('/^[0-9a-fA-F]{4}$/', $hex)) {
            return '';
        }

        $index += 4;

        return json_decode('"\\u'.$hex.'"', true, 512, JSON_THROW_ON_ERROR);
    }

    private function mergeUsage(array $current, array $next): array
    {
        foreach ($next as $key => $value) {
            if (is_numeric($value)) {
                $current[$key] = max((int) ($current[$key] ?? 0), (int) $value);
            }
        }

        return $current;
    }

    private function stripCodeFence(string $text): string
    {
        $text = trim($text);

        if (preg_match('/^```(?:html)?\s*(.*?)\s*```$/is', $text, $matches)) {
            return trim($matches[1]);
        }

        return $text;
    }

    private function startLlmOutputLog(TextRequest $request): ?string
    {
        if (! (bool) config('app.debug')) {
            return null;
        }

        $directory = storage_path('logs/llm-streams');

        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $stage = preg_replace('/[^A-Za-z0-9_-]+/', '_', $request->stage) ?: 'stream';
        $path = $directory.'/'.now('UTC')->format('Ymd_His')."_{$stage}_".bin2hex(random_bytes(4)).'.html';

        file_put_contents($path, '');

        return $path;
    }

    private function appendLlmOutputLog(?string $path, string $chunk): void
    {
        if ($path === null) {
            return;
        }

        file_put_contents($path, $chunk, FILE_APPEND | LOCK_EX);
    }

    private function finishLlmOutputLog(?string $path, TextRequest $request, string $text, array $usage, ?string $messageId, ?string $stopReason): void
    {
        if ($path === null) {
            return;
        }

        file_put_contents($path.'.meta.json', json_encode([
            'stage' => $request->stage,
            'provider' => $request->provider,
            'model' => $request->model,
            'message_id' => $messageId,
            'stop_reason' => $stopReason,
            'bytes' => strlen($text),
            'usage' => $usage,
            'written_at' => now('UTC')->toIso8601String(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    private function isModelNotFound(Throwable $exception): bool
    {
        $message = $exception->getMessage();

        return str_contains($message, 'not_found_error')
            && str_contains($message, 'model:');
    }

    private function fallbackRequest(StructuredRequest|TextRequest $request): StructuredRequest|TextRequest|null
    {
        $fallbackModel = app(LlmRegistry::class)->defaultModel($request->provider, $request->stage, $request->apiKey);

        if ($fallbackModel === '' || $fallbackModel === $request->model) {
            return null;
        }

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
}
