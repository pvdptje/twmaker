<?php

namespace App\Livewire\Setup;

use App\Services\Llm\LlmRegistry;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class LlmSetup extends Component
{
    /**
     * @var array<string, string>
     */
    public array $apiKeys = [];

    public string $primaryProvider = '';

    public string $primaryModel = '';

    public string $editingProvider = '';

    public string $editingModel = '';

    /**
     * @var array<string, string>
     */
    public array $modelCatalogStatuses = [];

    public string $saveStatus = '';

    public function mount(): void
    {
        $this->primaryProvider = $this->registry()->defaultProvider();
        $this->editingProvider = $this->primaryProvider;
        $this->primaryModel = $this->defaultModel($this->primaryProvider, 'section_generator');
        $this->editingModel = $this->defaultModel($this->editingProvider, 'targeted_edit');
    }

    public function updatedPrimaryProvider(): void
    {
        $this->primaryModel = $this->defaultModel($this->primaryProvider, 'section_generator');
        $this->saveStatus = '';
    }

    public function updatedEditingProvider(): void
    {
        $this->editingModel = $this->defaultModel($this->editingProvider, 'targeted_edit');
        $this->saveStatus = '';
    }

    public function refreshModels(string $provider): void
    {
        if (! in_array($provider, $this->providerIds(), true)) {
            return;
        }

        if (! $this->hasModelFetchKey($provider)) {
            $this->modelCatalogStatuses[$provider] = 'Add an API key to refresh models.';

            return;
        }

        $models = $this->registry()->refreshModelOptions($provider, $this->normalizedApiKey($provider));
        $this->ensureSelectedModelsAreAvailable($provider, $models);

        $this->modelCatalogStatuses[$provider] = count($models).' models refreshed.';
    }

    public function save(): void
    {
        $this->validate([
            'apiKeys' => ['array'],
            'apiKeys.*' => ['nullable', 'string', 'max:500'],
            'primaryProvider' => ['required', 'string', 'in:'.implode(',', $this->providerIds())],
            'primaryModel' => ['required', 'string', 'in:'.implode(',', $this->modelIds($this->primaryProvider))],
            'editingProvider' => ['required', 'string', 'in:'.implode(',', $this->providerIds())],
            'editingModel' => ['required', 'string', 'in:'.implode(',', $this->modelIds($this->editingProvider))],
        ]);

        $this->saveStatus = 'Setup saved on this browser.';
    }

    public function render(): View
    {
        return view()->file(__DIR__.'/llm-setup.blade.php', [
            'providerOptions' => $this->providerOptions(),
            'modelOptionsByProvider' => collect($this->providerIds())
                ->mapWithKeys(fn (string $provider): array => [$provider => $this->modelOptions($provider)])
                ->all(),
        ])->layout('components.layouts.app', ['title' => 'LLM setup']);
    }

    private function providerOptions(): array
    {
        return $this->registry()->implementedProviders();
    }

    private function providerIds(): array
    {
        return array_column($this->providerOptions(), 'id');
    }

    private function modelOptions(string $provider): array
    {
        return $this->registry()->modelOptions($provider, $this->normalizedApiKey($provider));
    }

    private function modelIds(string $provider): array
    {
        return array_column($this->modelOptions($provider), 'id');
    }

    private function defaultModel(string $provider, string $stage): string
    {
        return $this->registry()->defaultModel($provider, $stage, $this->normalizedApiKey($provider));
    }

    private function normalizedApiKey(string $provider): ?string
    {
        $apiKey = trim((string) ($this->apiKeys[$provider] ?? ''));

        return $apiKey === '' ? null : $apiKey;
    }

    private function hasModelFetchKey(string $provider): bool
    {
        if (! (bool) config("llm.providers.{$provider}.requires_api_key", true)) {
            return true;
        }

        return $this->normalizedApiKey($provider) !== null
            || trim((string) config("llm.providers.{$provider}.api_key")) !== '';
    }

    private function ensureSelectedModelsAreAvailable(string $provider, array $models): void
    {
        $modelIds = array_column($models, 'id');

        if ($this->primaryProvider === $provider && ! in_array($this->primaryModel, $modelIds, true)) {
            $this->primaryModel = $this->defaultModel($provider, 'section_generator');
        }

        if ($this->editingProvider === $provider && ! in_array($this->editingModel, $modelIds, true)) {
            $this->editingModel = $this->defaultModel($provider, 'targeted_edit');
        }
    }

    private function registry(): LlmRegistry
    {
        return app(LlmRegistry::class);
    }
}
