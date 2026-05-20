<?php

namespace App\Services\Llm;

use Anthropic\Client;
use Anthropic\RequestOptions;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class AnthropicProvider implements LlmProvider
{
    public function __construct(private readonly ?Client $client = null) {}

    public function sendStructured(StructuredRequest $request): StructuredResponse
    {
        $client = $this->client ?? new Client(
            apiKey: (string) config('llm.providers.anthropic.api_key'),
            requestOptions: RequestOptions::with(
                timeout: (float) config('llm.providers.anthropic.request_timeout', 600),
            ),
        );

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

    private function buildUserContent(StructuredRequest $request): string
    {
        return trim($request->userPrompt."\n\nContext JSON:\n".json_encode(
            $request->context,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        ));
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
}
