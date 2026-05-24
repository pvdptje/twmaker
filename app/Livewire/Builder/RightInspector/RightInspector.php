<?php

namespace App\Livewire\Builder\RightInspector;

use App\Models\Page;
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

        foreach ($this->page->generationEvents()->get(['payload']) as $event) {
            $llm = $event->payload['llm'] ?? null;

            if (! is_array($llm)) {
                continue;
            }

            $model = (string) ($llm['model'] ?? '');

            if ($model === '') {
                continue;
            }

            $usage = $this->normalizeUsage($llm['usage'] ?? []);

            $totals[$model] ??= ['input' => 0, 'output' => 0, 'cache' => 0, 'total' => 0];
            $totals[$model]['input'] += $usage['input'];
            $totals[$model]['output'] += $usage['output'];
            $totals[$model]['cache'] += $usage['cache'];
            $totals[$model]['total'] += $usage['total'];
        }

        ksort($totals);

        return $totals;
    }

    private function normalizeUsage(mixed $usage): array
    {
        if (is_object($usage)) {
            $usage = (array) $usage;
        }

        if (! is_array($usage)) {
            return ['input' => 0, 'output' => 0, 'cache' => 0, 'total' => 0];
        }

        $input = $this->usageInt($usage, ['prompt_tokens', 'input_tokens', 'inputTokens']);
        $output = $this->usageInt($usage, ['completion_tokens', 'output_tokens', 'outputTokens']);
        $cache = $this->usageInt($usage, ['cache_write_input_tokens', 'cache_creation_input_tokens', 'cacheCreationInputTokens'])
            + $this->usageInt($usage, ['cache_read_input_tokens', 'cacheReadInputTokens']);

        return [
            'input' => $input,
            'output' => $output,
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
