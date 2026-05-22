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
            [
                'id' => 'anthropic',
                'label' => 'Anthropic',
                'driver' => 'anthropic',
                'models_refreshed_at' => '2026-05-21',
            ],
            [
                'id' => 'deepseek',
                'label' => 'DeepSeek',
                'driver' => 'deepseek',
                'models_refreshed_at' => '2026-05-22',
            ],
        ], $registry->implementedProviders());

        $this->assertContains('claude-sonnet-4-6', $registry->modelIds('anthropic'));
        $this->assertNotContains('claude-3-7-sonnet-20250219', $registry->modelIds('anthropic'));
        $this->assertSame('claude-sonnet-4-6', $registry->defaultModel('anthropic', 'section_generator'));
        $this->assertContains('deepseek-v4-pro', $registry->modelIds('deepseek'));
        $this->assertSame('deepseek-v4-pro', $registry->defaultModel('deepseek', 'targeted_edit'));
    }

    public function test_merges_fetched_models_with_configured_fallback_models(): void
    {
        Cache::put('llm:models:deepseek:'.hash('sha256', 'test-key'), [
            ['id' => 'deepseek-v4-pro', 'label' => 'DeepSeek V4 Pro'],
        ], now()->addMinute());

        $registry = app(LlmRegistry::class);

        $this->assertContains('deepseek-v4-flash', $registry->modelIds('deepseek', 'test-key'));
    }
}
