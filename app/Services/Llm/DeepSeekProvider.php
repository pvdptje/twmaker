<?php

namespace App\Services\Llm;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use JsonException;
use RuntimeException;
use Throwable;

class DeepSeekProvider implements LlmProvider
{
    public function sendStructured(StructuredRequest $request): StructuredResponse
    {
        $userContent = $this->buildUserContent($request);
        $startedAt = microtime(true);

        Log::info('DeepSeek structured request started.', [
            'stage' => $request->stage,
            'model' => $request->model,
            'max_tokens' => $request->maxTokens,
            'prompt_bytes' => strlen($userContent),
        ]);

        try {
            $response = Http::withToken($this->apiKey($request))
                ->acceptJson()
                ->asJson()
                ->timeout((float) config("llm.providers.{$request->provider}.request_timeout", 600))
                ->post($this->baseUrl($request).'/chat/completions', [
                    'model' => $request->model,
                    'messages' => [
                        ['role' => 'system', 'content' => $this->systemPrompt($request)],
                        ['role' => 'user', 'content' => $userContent],
                    ],
                    'max_tokens' => $request->maxTokens,
                    'temperature' => $request->temperature,
                    'thinking' => ['type' => 'disabled'],
                    'tools' => [$this->toolDefinition($request)],
                    'tool_choice' => [
                        'type' => 'function',
                        'function' => ['name' => $request->toolName],
                    ],
                    'stream' => false,
                ]);

            if (! $response->successful()) {
                throw new RuntimeException('DeepSeek request failed with status '.$response->status().': '.$response->body());
            }

            $body = $response->json();
            if (! is_array($body)) {
                throw new RuntimeException('DeepSeek returned an invalid response body.');
            }

            $output = $this->scrubArray($this->extractToolArguments($body, $request->toolName));
        } catch (Throwable $exception) {
            Log::error('DeepSeek structured request failed.', [
                'stage' => $request->stage,
                'model' => $request->model,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        Log::info('DeepSeek structured request completed.', [
            'stage' => $request->stage,
            'model' => $request->model,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'finish_reason' => $body['choices'][0]['finish_reason'] ?? null,
        ]);

        return new StructuredResponse(
            stage: $request->stage,
            model: (string) ($body['model'] ?? $request->model),
            output: $output,
            raw: [
                'id' => $body['id'] ?? null,
                'finish_reason' => $body['choices'][0]['finish_reason'] ?? null,
            ],
            usage: $this->scrubArray(is_array($body['usage'] ?? null) ? $body['usage'] : []),
        );
    }

    public function sendTextStream(TextRequest $request, callable $onDelta): TextResponse
    {
        $userContent = $this->buildUserContent($request);
        $startedAt = microtime(true);
        $text = '';
        $usage = [];
        $messageId = null;
        $finishReason = null;
        $pendingChunk = '';
        $pendingPosition = 0;
        $lastFlushAt = microtime(true);
        $streamedText = '';
        $prefixBuffer = '';
        $prefixResolved = false;
        $flush = function () use (&$pendingChunk, &$pendingPosition, &$lastFlushAt, &$streamedText, &$prefixBuffer, &$prefixResolved, $onDelta, $request): void {
            if ($pendingChunk === '') {
                return;
            }

            $chunk = $this->stripStreamingCodeFencePrefix($pendingChunk, $prefixBuffer, $prefixResolved);
            $chunk = $this->stripStreamingCodeFenceSuffix($chunk);

            if ($chunk !== '') {
                $position = strlen($streamedText);
                $streamedText .= $chunk;
                $onDelta($chunk, $position, $request);
            }

            $pendingChunk = '';
            $lastFlushAt = microtime(true);
        };

        Log::info('DeepSeek text stream started.', [
            'stage' => $request->stage,
            'model' => $request->model,
            'max_tokens' => $request->maxTokens,
            'prompt_bytes' => strlen($userContent),
        ]);

        try {
            $response = Http::withToken($this->apiKey($request))
                ->acceptJson()
                ->asJson()
                ->timeout((float) config("llm.providers.{$request->provider}.request_timeout", 600))
                ->withOptions(['stream' => true])
                ->post($this->baseUrl($request).'/chat/completions', [
                    'model' => $request->model,
                    'messages' => [
                        ['role' => 'system', 'content' => $this->plainTextSystemPrompt($request)],
                        ['role' => 'user', 'content' => $userContent],
                    ],
                    'max_tokens' => $request->maxTokens,
                    'temperature' => $request->temperature,
                    'thinking' => ['type' => 'disabled'],
                    'stream' => true,
                    'stream_options' => ['include_usage' => true],
                ]);

            if (! $response->successful()) {
                throw new RuntimeException('DeepSeek stream failed with status '.$response->status().': '.$response->body());
            }

            foreach ($this->streamEvents($response->toPsrResponse()->getBody()) as $event) {
                if (($event['id'] ?? null) !== null) {
                    $messageId = (string) $event['id'];
                }

                if (is_array($event['usage'] ?? null)) {
                    $usage = $event['usage'];
                }

                $choice = $event['choices'][0] ?? null;
                if (! is_array($choice)) {
                    continue;
                }

                $finishReason = $choice['finish_reason'] ?? $finishReason;
                $chunk = $this->scrubText((string) ($choice['delta']['content'] ?? ''));
                if ($chunk === '') {
                    continue;
                }

                $position = strlen($text);
                $text .= $chunk;

                if ($pendingChunk === '') {
                    $pendingPosition = $position;
                }

                $pendingChunk .= $chunk;

                if (strlen($pendingChunk) >= 512 || microtime(true) - $lastFlushAt >= 0.75) {
                    $flush();
                }
            }

            $flush();
        } catch (Throwable $exception) {
            Log::error('DeepSeek text stream failed.', [
                'stage' => $request->stage,
                'model' => $request->model,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        Log::info('DeepSeek text stream completed.', [
            'stage' => $request->stage,
            'model' => $request->model,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'html_bytes' => strlen($text),
            'finish_reason' => $finishReason,
        ]);

        return new TextResponse(
            stage: $request->stage,
            model: $request->model,
            text: $this->stripCodeFence($this->scrubText($text)),
            raw: ['id' => $messageId, 'finish_reason' => $finishReason],
            usage: $this->scrubArray($usage),
        );
    }

    private function apiKey(StructuredRequest|TextRequest $request): string
    {
        $apiKey = trim((string) ($request->apiKey ?: config("llm.providers.{$request->provider}.api_key")));

        if ($apiKey === '') {
            throw new RuntimeException('DeepSeek API key is missing.');
        }

        return $apiKey;
    }

    private function baseUrl(StructuredRequest|TextRequest $request): string
    {
        return rtrim((string) config("llm.providers.{$request->provider}.base_url", 'https://api.deepseek.com'), '/');
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

    private function systemPrompt(StructuredRequest $request): string
    {
        return trim($request->systemPrompt."\n\nReturn the result by calling the {$request->toolName} function.");
    }

    private function plainTextSystemPrompt(TextRequest $request): string
    {
        return trim($request->systemPrompt."\n\nReturn the complete result directly as plain text. Do not use JSON, markdown, or code fences.");
    }

    private function toolDefinition(StructuredRequest $request): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $request->toolName,
                'description' => "Return the structured {$request->stage} result.",
                'parameters' => $request->schema,
            ],
        ];
    }

    private function extractToolArguments(array $body, string $toolName): array
    {
        $toolCalls = $body['choices'][0]['message']['tool_calls'] ?? [];

        foreach ($toolCalls as $toolCall) {
            if (($toolCall['type'] ?? null) !== 'function') {
                continue;
            }

            $function = $toolCall['function'] ?? [];
            if (($function['name'] ?? null) !== $toolName) {
                continue;
            }

            $arguments = $function['arguments'] ?? '{}';
            if (is_array($arguments)) {
                return $arguments;
            }

            $decoded = $this->decodeJsonArray((string) $arguments);

            return is_array($decoded) ? $decoded : [];
        }

        $content = (string) ($body['choices'][0]['message']['content'] ?? '');
        if (trim($content) !== '') {
            $decoded = $this->decodeJsonArray($content);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        throw new RuntimeException("DeepSeek response did not include expected function call [{$toolName}].");
    }

    private function streamEvents(mixed $stream): iterable
    {
        $buffer = '';

        while (! $stream->eof()) {
            $buffer .= $stream->read(8192);
            $buffer = str_replace("\r\n", "\n", $buffer);

            while (($separator = strpos($buffer, "\n\n")) !== false) {
                $rawEvent = substr($buffer, 0, $separator);
                $buffer = substr($buffer, $separator + 2);
                $event = $this->decodeStreamEvent($rawEvent);

                if ($event !== null) {
                    yield $event;
                }
            }
        }

        if (trim($buffer) !== '') {
            $event = $this->decodeStreamEvent($buffer);

            if ($event !== null) {
                yield $event;
            }
        }
    }

    private function decodeStreamEvent(string $rawEvent): ?array
    {
        $payload = '';
        $collectingData = false;

        foreach (preg_split('/\R/', $rawEvent) ?: [] as $line) {
            $line = rtrim($line, "\r\n");

            if (! str_starts_with($line, 'data:')) {
                if ($collectingData) {
                    $payload .= "\n".$line;
                }

                continue;
            }

            $collectingData = true;
            $data = trim(substr($line, 5));
            if ($data === '[DONE]') {
                return null;
            }

            $payload .= $data;
        }

        if ($payload === '') {
            return null;
        }

        try {
            $decoded = $this->decodeJsonArray($payload);
        } catch (JsonException $exception) {
            Log::warning('DeepSeek stream event could not be decoded; skipping malformed SSE event.', [
                'message' => $exception->getMessage(),
                'bytes' => strlen($payload),
            ]);

            return null;
        }

        return is_array($decoded) ? $decoded : null;
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

    private function sanitizeJson(string $value): string
    {
        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $this->scrubText($value)) ?? '';
    }

    private function decodeJsonArray(string $value): array
    {
        $json = $this->sanitizeJson($value);

        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
        } catch (JsonException $exception) {
            $decoded = json_decode($this->repairJsonStringControlCharacters($json), true, 512, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
        }

        return is_array($decoded) ? $decoded : [];
    }

    private function repairJsonStringControlCharacters(string $json): string
    {
        $repaired = '';
        $inString = false;
        $escaped = false;
        $length = strlen($json);

        for ($i = 0; $i < $length; $i++) {
            $char = $json[$i];
            $ord = ord($char);

            if ($escaped) {
                $repaired .= $char;
                $escaped = false;

                continue;
            }

            if ($char === '\\') {
                $repaired .= $char;
                $escaped = true;

                continue;
            }

            if ($char === '"') {
                $repaired .= $char;
                $inString = ! $inString;

                continue;
            }

            if ($ord < 32 || $ord === 127) {
                if ($inString) {
                    $repaired .= match ($char) {
                        "\n" => '\\n',
                        "\r" => '\\r',
                        "\t" => '\\t',
                        default => '',
                    };
                }

                continue;
            }

            $repaired .= $char;
        }

        return $repaired;
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
