<?php

namespace Tests\Unit\Llm;

use App\Services\Llm\LlmRegistry;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class LlmRegistryTest extends TestCase
{
    public function test_lists_only_implemented_providers_and_their_models(): void
    {
        $registry = app(LlmRegistry::class);

        $this->assertSame([
            'anthropic',
            'deepseek',
            'openai',
            'openrouter',
            'ollama',
            'mistral',
            'groq',
            'xai',
            'gemini',
            'perplexity',
            'z',
        ], array_column($registry->implementedProviders(), 'id'));

        $this->assertSame([], $registry->modelIds('anthropic'));
        $this->assertNotContains('claude-3-7-sonnet-20250219', $registry->modelIds('anthropic'));
        $this->assertSame('', $registry->defaultModel('anthropic', 'section_generator'));
        $this->assertSame([], $registry->modelIds('openai'));
        $this->assertSame('', $registry->defaultModel('deepseek', 'targeted_edit'));
    }

    public function test_uses_fetched_models_without_configured_fallback_models(): void
    {
        Cache::put('llm:models:deepseek:'.hash('sha256', 'test-key'), [
            ['id' => 'deepseek-chat', 'label' => 'DeepSeek Chat'],
        ], now()->addMinute());

        $registry = app(LlmRegistry::class);

        $this->assertSame(['deepseek-chat'], $registry->modelIds('deepseek', 'test-key'));
        $this->assertSame('deepseek-chat', $registry->defaultModel('deepseek', 'targeted_edit', 'test-key'));
    }
}
