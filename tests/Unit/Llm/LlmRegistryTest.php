<?php

namespace Tests\Unit\Llm;

use App\Services\Llm\LlmRegistry;
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
        ], $registry->implementedProviders());

        $this->assertContains('claude-sonnet-4-6', $registry->modelIds('anthropic'));
        $this->assertNotContains('claude-3-7-sonnet-20250219', $registry->modelIds('anthropic'));
        $this->assertSame('claude-sonnet-4-6', $registry->defaultModel('anthropic', 'section_generator'));
    }
}
