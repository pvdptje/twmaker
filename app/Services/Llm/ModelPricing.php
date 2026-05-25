<?php

namespace App\Services\Llm;

class ModelPricing
{
    /**
     * @param  array{input: int, output: int, cache?: int, cache_write?: int, cache_read?: int}  $usage
     * @return array{amount: float, currency: string}|null
     */
    public function estimate(string $provider, string $model, array $usage): ?array
    {
        $pricing = $this->pricing($provider, $model);

        if ($pricing === null) {
            return null;
        }

        $unitTokens = max(1, (int) config('llm_pricing.unit_tokens', 1_000_000));
        $amount = 0.0;
        $amount += $this->tokenCost($usage['input'] ?? 0, $pricing['input'] ?? 0, $unitTokens);
        $amount += $this->tokenCost($usage['output'] ?? 0, $pricing['output'] ?? 0, $unitTokens);
        $amount += $this->tokenCost($usage['cache_write'] ?? 0, $pricing['cache_write'] ?? ($pricing['input'] ?? 0), $unitTokens);
        $amount += $this->tokenCost($usage['cache_read'] ?? 0, $pricing['cache_read'] ?? ($pricing['input'] ?? 0), $unitTokens);

        return [
            'amount' => round($amount, 6),
            'currency' => (string) config('llm_pricing.currency', 'USD'),
        ];
    }

    /**
     * @return array<string, float|int>|null
     */
    private function pricing(string $provider, string $model): ?array
    {
        $models = config('llm_pricing.models', []);
        $pricing = is_array($models) ? ($models[$provider][$model] ?? null) : null;

        if (! is_array($pricing)) {
            return null;
        }

        return $pricing;
    }

    private function tokenCost(int $tokens, mixed $pricePerUnit, int $unitTokens): float
    {
        if ($tokens <= 0 || ! is_numeric($pricePerUnit)) {
            return 0.0;
        }

        return ($tokens / $unitTokens) * (float) $pricePerUnit;
    }
}
