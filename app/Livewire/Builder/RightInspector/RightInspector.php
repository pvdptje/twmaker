<?php

namespace App\Livewire\Builder\RightInspector;

use App\Models\Page;
use App\Services\Llm\ModelPricing;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Reactive;
use Livewire\Component;

class RightInspector extends Component
{
    public Page $page;

    #[Reactive]
    public ?string $selectedNodeId = null;

    #[Reactive]
    public array $selectedBlockIds = [];

    public function render(): View
    {
        return view()->file(__DIR__.'/right-inspector.blade.php', [
            'usageTotals' => $this->usageTotals(),
        ]);
    }

    private function usageTotals(): array
    {
        $totals = [];

        $events = $this->page->generationEvents()
            ->whereNotNull('payload->llm->model')
            ->select(['payload'])
            ->cursor();

        foreach ($events as $event) {
            $llm = $event->payload['llm'] ?? null;

            if (! is_array($llm)) {
                continue;
            }

            $model = (string) ($llm['model'] ?? '');

            if ($model === '') {
                continue;
            }

            $provider = (string) ($llm['provider'] ?? '');
            $usage = $this->normalizeUsage($llm['usage'] ?? []);
            $key = "{$provider}:{$model}";

            $totals[$key] ??= [
                'provider' => $provider,
                'model' => $model,
                'input' => 0,
                'output' => 0,
                'cache_write' => 0,
                'cache_read' => 0,
                'cache' => 0,
                'total' => 0,
                'cost' => null,
            ];
            $totals[$key]['input'] += $usage['input'];
            $totals[$key]['output'] += $usage['output'];
            $totals[$key]['cache_write'] += $usage['cache_write'];
            $totals[$key]['cache_read'] += $usage['cache_read'];
            $totals[$key]['cache'] += $usage['cache'];
            $totals[$key]['total'] += $usage['total'];
        }

        ksort($totals);

        $pricing = app(ModelPricing::class);

        return array_map(function (array $usage) use ($pricing): array {
            $usage['cost'] = $pricing->estimate($usage['provider'], $usage['model'], $usage);

            return $usage;
        }, $totals);
    }

    private function normalizeUsage(mixed $usage): array
    {
        if (is_object($usage)) {
            $usage = (array) $usage;
        }

        if (! is_array($usage)) {
            return [
                'input' => 0,
                'output' => 0,
                'cache_write' => 0,
                'cache_read' => 0,
                'cache' => 0,
                'total' => 0,
            ];
        }

        $input = $this->usageInt($usage, ['prompt_tokens', 'input_tokens', 'inputTokens']);
        $output = $this->usageInt($usage, ['completion_tokens', 'output_tokens', 'outputTokens']);
        $cacheWrite = $this->usageInt($usage, ['cache_write_input_tokens', 'cache_creation_input_tokens', 'cacheCreationInputTokens']);
        $cacheRead = $this->usageInt($usage, ['cache_read_input_tokens', 'cacheReadInputTokens']);
        $cache = $cacheWrite + $cacheRead;

        return [
            'input' => $input,
            'output' => $output,
            'cache_write' => $cacheWrite,
            'cache_read' => $cacheRead,
            'cache' => $cache,
            'total' => $input + $output + $cache,
        ];
    }

    private function usageInt(array $usage, array $keys): int
    {
        foreach ($keys as $key) {
            if (isset($usage[$key]) && is_numeric($usage[$key])) {
                return (int) $usage[$key];
            }
        }

        return 0;
    }
}
