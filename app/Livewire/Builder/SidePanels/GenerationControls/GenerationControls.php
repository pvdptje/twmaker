<?php

namespace App\Livewire\Builder\SidePanels\GenerationControls;

use App\Jobs\GeneratePageJob;
use App\Models\Page;
use App\Services\Llm\LlmRegistry;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class GenerationControls extends Component
{
    public Page $page;

    public string $prompt = '';

    public string $provider = '';

    public string $model = '';

    public string $apiKey = '';

    public string $modelCatalogStatus = '';

    public function mount(Page $page): void
    {
        $this->prompt = $page->prompt;
        $this->provider = $this->registry()->defaultProvider();
        $this->model = $this->defaultModel();
    }

    public function updatedProvider(): void
    {
        $this->model = $this->defaultModel();
        $this->modelCatalogStatus = '';
    }

    public function updatedApiKey(): void
    {
        $this->ensureSelectedModelIsAvailable();
        $this->modelCatalogStatus = '';
    }

    public function refreshModels(): void
    {
        if (! $this->hasModelFetchKey()) {
            $this->modelCatalogStatus = 'Add an API key to refresh provider models.';

            return;
        }

        $models = $this->registry()->refreshModelOptions($this->provider, $this->normalizedApiKey());
        $this->ensureSelectedModelIsAvailable($models);

        $this->modelCatalogStatus = count($models).' models refreshed.';
    }

    public function generate(): void
    {
        $this->validate([
            'prompt' => ['required', 'string', 'min:3', 'max:5000'],
            'provider' => ['required', 'string', 'in:'.implode(',', $this->providerIds())],
            'model' => ['required', 'string', 'in:'.implode(',', $this->modelIds())],
            'apiKey' => ['nullable', 'string', 'max:500'],
        ]);

        $this->page->forceFill([
            'prompt' => $this->prompt,
            'status' => 'generating',
        ])->save();

        $this->dispatch('generation-started', pageId: $this->page->id);

        GeneratePageJob::dispatch($this->page->id, $this->provider, $this->model, $this->normalizedApiKey());

        $this->dispatch('generation-finished', pageId: $this->page->id, status: $this->page->refresh()->status);
    }

    public function render(): View
    {
        return view()->file(__DIR__.'/generation-controls.blade.php', [
            'providerOptions' => $this->providerOptions(),
            'modelOptions' => $this->modelOptions(),
        ]);
    }

    private function providerOptions(): array
    {
        return $this->registry()->implementedProviders();
    }

    private function providerIds(): array
    {
        return array_column($this->providerOptions(), 'id');
    }

    private function modelOptions(): array
    {
        return $this->registry()->modelOptions($this->provider, $this->normalizedApiKey());
    }

    private function modelIds(): array
    {
        return array_column($this->modelOptions(), 'id');
    }

    private function defaultModel(): string
    {
        return $this->registry()->defaultModel($this->provider, 'section_generator', $this->normalizedApiKey());
    }

    private function normalizedApiKey(): ?string
    {
        $apiKey = trim($this->apiKey);

        return $apiKey === '' ? null : $apiKey;
    }

    private function registry(): LlmRegistry
    {
        return app(LlmRegistry::class);
    }

    private function ensureSelectedModelIsAvailable(?array $models = null): void
    {
        $modelIds = array_column($models ?? $this->modelOptions(), 'id');

        if (! in_array($this->model, $modelIds, true)) {
            $this->model = $this->defaultModel();
        }
    }

    private function hasModelFetchKey(): bool
    {
        return $this->normalizedApiKey() !== null
            || trim((string) config("llm.providers.{$this->provider}.api_key")) !== '';
    }
}
