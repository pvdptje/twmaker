<?php

namespace Tests\Unit\Llm;

use App\Services\Llm\ModelCapabilities;
use PHPUnit\Framework\TestCase;

class ModelCapabilitiesTest extends TestCase
{
    public function test_text_only_for_unknown_provider(): void
    {
        $this->assertSame(['text'], $this->detector()->detect('mystery', 'whatever'));
    }

    public function test_claude_3_and_4_are_vision_capable(): void
    {
        $detector = $this->detector();

        $this->assertSame(['text', 'image'], $detector->detect('anthropic', 'claude-3-5-sonnet-latest'));
        $this->assertSame(['text', 'image'], $detector->detect('anthropic', 'claude-opus-4-7'));
        $this->assertSame(['text'], $detector->detect('anthropic', 'claude-2.1'));
    }

    public function test_openai_vision_allowlist(): void
    {
        $detector = $this->detector();

        $this->assertContains('image', $detector->detect('openai', 'gpt-4o'));
        $this->assertContains('image', $detector->detect('openai', 'gpt-4.1-mini'));
        $this->assertNotContains('image', $detector->detect('openai', 'gpt-3.5-turbo'));
        $this->assertNotContains('image', $detector->detect('openai', 'o1-mini'));
    }

    public function test_deepseek_is_text_only(): void
    {
        $this->assertSame(['text'], $this->detector()->detect('deepseek', 'deepseek-chat'));
        $this->assertSame(['text'], $this->detector()->detect('deepseek', 'deepseek-v4-flash'));
    }

    public function test_openrouter_payload_modalities_take_precedence(): void
    {
        $payload = [
            'id' => 'meta-llama/llama-3-text',
            'architecture' => ['input_modalities' => ['text', 'image']],
        ];

        $this->assertSame(
            ['text', 'image'],
            $this->detector()->detect('openrouter', 'meta-llama/llama-3-text', $payload),
        );
    }

    public function test_gemini_payload_with_supported_methods(): void
    {
        $payload = [
            'name' => 'models/gemini-2.5-pro',
            'supportedGenerationMethods' => ['generateContent', 'countTokens'],
        ];

        $this->assertSame(
            ['text', 'image'],
            $this->detector()->detect('gemini', 'gemini-2.5-pro', $payload),
        );
    }

    public function test_ollama_vision_model_names(): void
    {
        $detector = $this->detector();

        $this->assertContains('image', $detector->detect('ollama', 'llava:13b'));
        $this->assertContains('image', $detector->detect('ollama', 'llama3.2-vision:11b'));
        $this->assertNotContains('image', $detector->detect('ollama', 'llama3.2:3b'));
    }

    private function detector(): ModelCapabilities
    {
        return new ModelCapabilities;
    }
}
