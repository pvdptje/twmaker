<?php

namespace App\Services\Llm;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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

    public function sendTextStream(StructuredRequest $request, callable $onDelta): StructuredResponse
    {
        $userContent = $this->buildUserContent($request);
        $startedAt = microtime(true);
        $text = '';
        $usage = [];
        $messageId = null;
        $finishReason = null;

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
                $onDelta($chunk, $position, $request);
            }
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

        return new StructuredResponse(
            stage: $request->stage,
            model: $request->model,
            output: ['raw_html' => $this->stripCodeFence($this->scrubText($text))],
            raw: ['id' => $messageId, 'finish_reason' => $finishReason],
            usage: $this->scrubArray($usage),
        );
    }

    private function apiKey(StructuredRequest $request): string
    {
        $apiKey = trim((string) ($request->apiKey ?: config("llm.providers.{$request->provider}.api_key")));

        if ($apiKey === '') {
            throw new RuntimeException('DeepSeek API key is missing.');
        }

        return $apiKey;
    }

    private function baseUrl(StructuredRequest $request): string
    {
        return rtrim((string) config("llm.providers.{$request->provider}.base_url", 'https://api.deepseek.com'), '/');
    }

    private function buildUserContent(StructuredRequest $request): string
    {
        return trim($request->userPrompt."\n\nContext JSON:\n".json_encode(
            $request->context,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        ));
    }

    private function systemPrompt(StructuredRequest $request): string
    {
        return trim($request->systemPrompt."\n\nReturn the result by calling the {$request->toolName} function.");
    }

    private function plainTextSystemPrompt(StructuredRequest $request): string
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

            $decoded = json_decode($this->scrubText((string) $arguments), true, 512, JSON_THROW_ON_ERROR);

            return is_array($decoded) ? $decoded : [];
        }

        $content = (string) ($body['choices'][0]['message']['content'] ?? '');
        if (trim($content) !== '') {
            $decoded = json_decode($this->scrubText($content), true, 512, JSON_THROW_ON_ERROR);

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

        foreach (preg_split('/\R/', $rawEvent) ?: [] as $line) {
            $line = trim($line);

            if (! str_starts_with($line, 'data:')) {
                continue;
            }

            $data = trim(substr($line, 5));
            if ($data === '[DONE]') {
                return null;
            }

            $payload .= $data;
        }

        if ($payload === '') {
            return null;
        }

        $decoded = json_decode($this->scrubText($payload), true, 512, JSON_THROW_ON_ERROR);

        return is_array($decoded) ? $decoded : null;
    }

    private function stripCodeFence(string $text): string
    {
        $text = trim($text);

        if (preg_match('/^```(?:html)?\s*(.*?)\s*```$/is', $text, $matches)) {
            return trim($matches[1]);
        }

        return $text;
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
