<?php

namespace Tests\Unit\Llm;

use App\Services\Llm\PrismProvider;
use App\Services\Llm\StructuredRequest;
use App\Services\Llm\TextRequest;
use RuntimeException;
use Tests\TestCase;

class PrismProviderTest extends TestCase
{
    public function test_omits_temperature_for_anthropic_opus_4_7_models(): void
    {
        $provider = new PrismProvider;
        $request = new TextRequest(
            stage: 'section_inserter',
            provider: 'anthropic',
            model: 'claude-opus-4-7-20260501',
            systemPrompt: 'System',
            userPrompt: 'User',
            temperature: 0.6,
        );

        $this->assertNull($this->invoke($provider, 'temperatureFor', $request));
    }

    public function test_keeps_temperature_for_other_models(): void
    {
        $provider = new PrismProvider;
        $request = new TextRequest(
            stage: 'section_inserter',
            provider: 'anthropic',
            model: 'claude-sonnet-4-20250514',
            systemPrompt: 'System',
            userPrompt: 'User',
            temperature: 0.6,
        );

        $this->assertSame(0.6, $this->invoke($provider, 'temperatureFor', $request));
    }

    public function test_temperature_deprecation_retry_preserves_text_request_payload(): void
    {
        $provider = new PrismProvider;
        $request = new TextRequest(
            stage: 'targeted_edit',
            provider: 'anthropic',
            model: 'claude-future-20260601',
            systemPrompt: 'System',
            userPrompt: 'User',
            context: ['page_id' => 'page_1'],
            maxTokens: 8000,
            temperature: 0.4,
            apiKey: 'test-key',
            images: [['base64' => 'abc', 'mime_type' => 'image/png']],
        );

        $retry = $this->invoke(
            $provider,
            'temperatureRetryRequest',
            $request,
            new RuntimeException('Anthropic Error [400]: invalid_request_error - `temperature` is deprecated for this model.'),
        );

        $this->assertInstanceOf(TextRequest::class, $retry);
        $this->assertNull($retry->temperature);
        $this->assertSame($request->images, $retry->images);
        $this->assertSame($request->context, $retry->context);
        $this->assertSame($request->apiKey, $retry->apiKey);
    }

    public function test_temperature_deprecation_retry_preserves_structured_request_payload(): void
    {
        $provider = new PrismProvider;
        $request = new StructuredRequest(
            stage: 'html_marker',
            provider: 'anthropic',
            model: 'claude-future-20260601',
            systemPrompt: 'System',
            userPrompt: 'User',
            toolName: 'submit_marked_html_document',
            schema: ['type' => 'object', 'properties' => []],
            context: ['page_id' => 'page_1'],
            maxTokens: 8000,
            temperature: 0.2,
            apiKey: 'test-key',
        );

        $retry = $this->invoke(
            $provider,
            'temperatureRetryRequest',
            $request,
            new RuntimeException('Anthropic Error [400]: invalid_request_error - temperature is deprecated for this model.'),
        );

        $this->assertInstanceOf(StructuredRequest::class, $retry);
        $this->assertNull($retry->temperature);
        $this->assertSame($request->schema, $retry->schema);
        $this->assertSame($request->context, $retry->context);
        $this->assertSame($request->apiKey, $retry->apiKey);
    }

    private function invoke(PrismProvider $provider, string $method, mixed ...$args): mixed
    {
        $reflection = new \ReflectionMethod($provider, $method);
        $reflection->setAccessible(true);

        return $reflection->invoke($provider, ...$args);
    }
}
