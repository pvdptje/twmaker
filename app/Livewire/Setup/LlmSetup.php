<?php

namespace App\Livewire\Setup;

use App\Models\Team;
use App\Services\Llm\LlmRegistry;
use App\Services\Llm\TeamProviderCredentials;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;

class LlmSetup extends Component
{
    public string $providerToAdd = '';

    public string $newApiKey = '';

    /**
     * @var array<string, string>
     */
    public array $replacementKeys = [];

    public string $primaryProvider = '';

    public string $primaryModel = '';

    /**
     * @var array<string, string>
     */
    public array $modelCatalogStatuses = [];

    public string $saveStatus = '';

    public function mount(): void
    {
        $this->resetSelection();
    }

    public function updatedPrimaryProvider(): void
    {
        $this->primaryModel = $this->defaultModel($this->primaryProvider);
        $this->saveStatus = '';
    }

    public function updatedPrimaryModel(): void
    {
        $this->saveStatus = '';
    }

    public function addProvider(): void
    {
        $team = $this->team();
        $providerIds = $this->providerIds();
        $requiresKey = $this->providerToAdd !== ''
            && (bool) config("llm.providers.{$this->providerToAdd}.requires_api_key", true)
            && trim((string) config("llm.providers.{$this->providerToAdd}.api_key")) === '';

        $this->validate([
            'providerToAdd' => ['required', 'string', Rule::in($providerIds)],
            'newApiKey' => [Rule::requiredIf($requiresKey), 'nullable', 'string', 'max:500'],
        ]);

        $this->credentials()->save($team, $this->providerToAdd, $this->newApiKey);

        $provider = $this->providerToAdd;
        $this->providerToAdd = '';
        $this->newApiKey = '';
        $this->saveStatus = config("llm.providers.{$provider}.label", ucfirst($provider)).' added to this team.';

        if ($this->credentials()->canFetchModels($team, $provider)) {
            $this->refreshModels($provider);
        }

        $this->resetSelection($provider);
    }

    public function removeProvider(string $provider): void
    {
        if (! in_array($provider, $this->configuredProviderIds(), true)) {
            return;
        }

        $this->credentials()->delete($this->team(), $provider);
        unset($this->modelCatalogStatuses[$provider]);
        $this->saveStatus = config("llm.providers.{$provider}.label", ucfirst($provider)).' removed from this team.';
        $this->resetSelection();
    }

    public function replaceProviderKey(string $provider): void
    {
        if (! in_array($provider, $this->configuredProviderIds(), true)) {
            return;
        }

        $this->validate([
            "replacementKeys.{$provider}" => ['required', 'string', 'max:500'],
        ]);

        $this->credentials()->save($this->team(), $provider, $this->replacementKeys[$provider] ?? '');
        $this->replacementKeys[$provider] = '';
        $this->saveStatus = config("llm.providers.{$provider}.label", ucfirst($provider)).' key replaced.';
        $this->refreshModels($provider);
        $this->resetSelection($provider);
    }

    public function reloadModels(): void
    {
        $configuredProviderIds = $this->configuredProviderIds();

        if ($configuredProviderIds === []) {
            $this->saveStatus = 'Add a provider before reloading models.';

            return;
        }

        foreach ($configuredProviderIds as $provider) {
            $this->refreshModels($provider);
        }

        $this->saveStatus = 'Model catalogs reloaded.';
    }

    public function refreshModels(string $provider): void
    {
        if (! in_array($provider, $this->configuredProviderIds(), true)) {
            return;
        }

        $team = $this->team();

        if (! $this->credentials()->canFetchModels($team, $provider)) {
            $this->modelCatalogStatuses[$provider] = 'Add an API key to reload models.';

            return;
        }

        $models = $this->registry()->refreshModelOptions($provider, $this->apiKey($provider));
        $this->ensureSelectedModelIsAvailable($provider, $models);

        $this->modelCatalogStatuses[$provider] = count($models).' models refreshed.';
    }

    public function save(): void
    {
        $configuredProviderIds = $this->configuredProviderIds();

        $this->validate([
            'primaryProvider' => ['required', 'string', Rule::in($configuredProviderIds)],
            'primaryModel' => ['required', 'string', Rule::in($this->modelIds($this->primaryProvider))],
        ]);

        session([
            'builder.primary.provider' => $this->primaryProvider,
            'builder.editing.provider' => $this->primaryProvider,
            "builder.primary.models.{$this->primaryProvider}" => $this->primaryModel,
            "builder.editing.models.{$this->primaryProvider}" => $this->primaryModel,
        ]);

        $this->saveStatus = 'Setup saved for this team.';
    }

    public function render(): View
    {
        $team = $this->team();
        $configuredProviderOptions = $this->credentials()->configuredProviderOptions($team);

        return view()->file(__DIR__.'/llm-setup.blade.php', [
            'configuredProviderOptions' => $configuredProviderOptions,
            'availableProviderOptions' => $this->credentials()->availableProviderOptions($team),
            'modelOptionsByProvider' => collect(array_column($configuredProviderOptions, 'id'))
                ->mapWithKeys(fn (string $provider): array => [$provider => $this->modelOptions($provider)])
                ->all(),
        ])->layout('components.layouts.app', ['title' => 'LLM setup']);
    }

    private function resetSelection(?string $preferredProvider = null): void
    {
        $providerIds = $this->configuredProviderIds();
        $preferredProvider = is_string($preferredProvider) && in_array($preferredProvider, $providerIds, true)
            ? $preferredProvider
            : null;

        $this->primaryProvider = $preferredProvider
            ?? (in_array($this->primaryProvider, $providerIds, true) ? $this->primaryProvider : (string) ($providerIds[0] ?? ''));
        $this->primaryModel = $this->primaryProvider !== '' ? $this->defaultModel($this->primaryProvider) : '';
    }

    private function providerIds(): array
    {
        return array_column($this->registry()->implementedProviders(), 'id');
    }

    private function configuredProviderIds(): array
    {
        return array_column($this->credentials()->configuredProviderOptions($this->team()), 'id');
    }

    private function modelOptions(string $provider): array
    {
        return $this->registry()->modelOptions($provider, $this->apiKey($provider));
    }

    private function modelIds(string $provider): array
    {
        return array_column($this->modelOptions($provider), 'id');
    }

    private function defaultModel(string $provider): string
    {
        return $this->registry()->defaultModel($provider, 'section_generator', $this->apiKey($provider));
    }

    private function apiKey(string $provider): ?string
    {
        return $this->credentials()->apiKey($this->team(), $provider);
    }

    private function ensureSelectedModelIsAvailable(string $provider, array $models): void
    {
        $modelIds = array_column($models, 'id');

        if ($this->primaryProvider === $provider && ! in_array($this->primaryModel, $modelIds, true)) {
            $this->primaryModel = $this->defaultModel($provider);
        }
    }

    private function team(): Team
    {
        return $this->credentials()->currentTeam();
    }

    private function credentials(): TeamProviderCredentials
    {
        return app(TeamProviderCredentials::class);
    }

    private function registry(): LlmRegistry
    {
        return app(LlmRegistry::class);
    }
}
