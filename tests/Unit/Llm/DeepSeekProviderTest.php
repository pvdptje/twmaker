<?php

namespace Tests\Unit\Llm;

use App\Services\Llm\DeepSeekProvider;
use App\Services\Llm\StructuredRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DeepSeekProviderTest extends TestCase
{
    public function test_sends_structured_request_with_forced_function_call(): void
    {
        Http::fake([
            'https://api.deepseek.com/chat/completions' => Http::response([
                'id' => 'chatcmpl-test',
                'model' => 'deepseek-v4-pro',
                'choices' => [
                    [
                        'finish_reason' => 'tool_calls',
                        'message' => [
                            'tool_calls' => [
                                [
                                    'type' => 'function',
                                    'function' => [
                                        'name' => 'submit_test',
                                        'arguments' => json_encode(['html_source' => '<section>DeepSeek</section>']),
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                'usage' => [
                    'prompt_tokens' => 12,
                    'completion_tokens' => 8,
                    'total_tokens' => 20,
                ],
            ]),
        ]);

        $response = (new DeepSeekProvider)->sendStructured(new StructuredRequest(
            stage: 'html_marker',
            provider: 'deepseek',
            model: 'deepseek-v4-pro',
            systemPrompt: 'Return marked HTML.',
            userPrompt: 'A simple page',
            toolName: 'submit_test',
            schema: [
                'type' => 'object',
                'required' => ['html_source'],
                'properties' => [
                    'html_source' => ['type' => 'string'],
                ],
            ],
            maxTokens: 8000,
            apiKey: 'test-deepseek-key',
        ));

        $this->assertSame(['html_source' => '<section>DeepSeek</section>'], $response->output);
        $this->assertSame('deepseek-v4-pro', $response->model);
        $this->assertSame(20, $response->usage['total_tokens']);

        Http::assertSent(function ($request): bool {
            $body = $request->data();

            return $request->hasHeader('Authorization', 'Bearer test-deepseek-key')
                && $body['model'] === 'deepseek-v4-pro'
                && $body['thinking'] === ['type' => 'disabled']
                && $body['tool_choice']['function']['name'] === 'submit_test'
                && $body['tools'][0]['function']['parameters']['required'] === ['html_source'];
        });
    }

    public function test_streams_plain_text_generation_without_tools(): void
    {
        Http::fake([
            'https://api.deepseek.com/chat/completions' => Http::response(implode("\n\n", [
                'data: '.json_encode([
                    'id' => 'chatcmpl-stream',
                    'choices' => [
                        ['delta' => ['content' => '<section>']],
                    ],
                ]),
                'data: '.json_encode([
                    'choices' => [
                        ['delta' => ['content' => 'Hello</section>'], 'finish_reason' => 'stop'],
                    ],
                ]),
                'data: '.json_encode([
                    'choices' => [],
                    'usage' => ['total_tokens' => 14],
                ]),
                'data: [DONE]',
            ]), 200, ['Content-Type' => 'text/event-stream']),
        ]);

        $chunks = [];
        $response = (new DeepSeekProvider)->sendTextStream(new StructuredRequest(
            stage: 'section_generator',
            provider: 'deepseek',
            model: 'deepseek-v4-flash',
            systemPrompt: 'Return raw HTML.',
            userPrompt: 'A simple page',
            toolName: 'submit_raw_html_document',
            schema: [
                'type' => 'object',
                'required' => ['raw_html'],
                'properties' => [
                    'raw_html' => ['type' => 'string'],
                ],
            ],
            maxTokens: 8000,
            apiKey: 'test-deepseek-key',
        ), function (string $chunk, int $position) use (&$chunks): void {
            $chunks[] = [$chunk, $position];
        });

        $this->assertSame(['raw_html' => '<section>Hello</section>'], $response->output);
        $this->assertSame([
            ['<section>Hello</section>', 0],
        ], $chunks);
        $this->assertSame(14, $response->usage['total_tokens']);

        Http::assertSent(function ($request): bool {
            $body = $request->data();

            return $body['stream'] === true
                && $body['thinking'] === ['type' => 'disabled']
                && ! isset($body['tools'])
                && ! isset($body['tool_choice']);
        });
    }

    public function test_stream_scrubs_malformed_utf8_payloads(): void
    {
        $malformedPayload = '{"id":"chatcmpl-stream","choices":[{"delta":{"content":"Broken '.chr(195).'("}}]}';

        Http::fake([
            'https://api.deepseek.com/chat/completions' => Http::response(implode("\n\n", [
                'data: '.$malformedPayload,
                'data: [DONE]',
            ]), 200, ['Content-Type' => 'text/event-stream']),
        ]);

        $chunks = [];
        $response = (new DeepSeekProvider)->sendTextStream(new StructuredRequest(
            stage: 'section_generator',
            provider: 'deepseek',
            model: 'deepseek-v4-flash',
            systemPrompt: 'Return raw HTML.',
            userPrompt: 'A simple page',
            toolName: 'submit_raw_html_document',
            schema: [
                'type' => 'object',
                'required' => ['raw_html'],
                'properties' => [
                    'raw_html' => ['type' => 'string'],
                ],
            ],
            maxTokens: 8000,
            apiKey: 'test-deepseek-key',
        ), function (string $chunk, int $position) use (&$chunks): void {
            $chunks[] = [$chunk, $position];
        });

        $this->assertTrue(mb_check_encoding($response->output['raw_html'], 'UTF-8'));
        $this->assertStringNotContainsString(chr(195).'(', $response->output['raw_html']);
        $this->assertSame(0, $chunks[0][1]);
        $this->assertTrue(mb_check_encoding($chunks[0][0], 'UTF-8'));
    }

    public function test_stream_strips_raw_control_characters_before_decoding(): void
    {
        $controlCharacterPayload = '{"id":"chatcmpl-stream","choices":[{"delta":{"content":"Broken '.chr(1).' control"}}]}';

        Http::fake([
            'https://api.deepseek.com/chat/completions' => Http::response(implode("\n\n", [
                'data: '.$controlCharacterPayload,
                'data: [DONE]',
            ]), 200, ['Content-Type' => 'text/event-stream']),
        ]);

        $response = (new DeepSeekProvider)->sendTextStream(new StructuredRequest(
            stage: 'section_generator',
            provider: 'deepseek',
            model: 'deepseek-v4-flash',
            systemPrompt: 'Return raw HTML.',
            userPrompt: 'A simple page',
            toolName: 'submit_raw_html_document',
            schema: [
                'type' => 'object',
                'required' => ['raw_html'],
                'properties' => [
                    'raw_html' => ['type' => 'string'],
                ],
            ],
            maxTokens: 8000,
            apiKey: 'test-deepseek-key',
        ), fn (): null => null);

        $this->assertSame(['raw_html' => 'Broken  control'], $response->output);
    }
}
