<?php

namespace Tests\Unit\Llm;

use App\Services\Llm\ModelPricing;
use Tests\TestCase;

class ModelPricingTest extends TestCase
{
    public function test_estimates_models_with_dots_in_their_ids(): void
    {
        config([
            'llm_pricing.models' => [
                'openai' => [
                    'gpt-4.1-mini' => [
                        'input' => 1.00,
                        'output' => 4.00,
                    ],
                ],
            ],
        ]);

        $cost = app(ModelPricing::class)->estimate('openai', 'gpt-4.1-mini', [
            'input' => 1_000_000,
            'output' => 500_000,
        ]);

        $this->assertSame([
            'amount' => 3.0,
            'currency' => 'USD',
        ], $cost);
    }

    public function test_returns_null_for_unconfigured_models(): void
    {
        $this->assertNull(app(ModelPricing::class)->estimate('openai', 'unknown-model', [
            'input' => 1_000_000,
            'output' => 1_000_000,
        ]));
    }
}
