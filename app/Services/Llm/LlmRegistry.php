<?php

namespace App\Services\Llm;

class LlmRegistry
{
    public function __construct(
        private readonly ProviderModelCatalog $catalog,
        private readonly ModelCapabilities $capabilities = new ModelCapabilities,
    ) {}

    public function implementedProviders(): array
    {
        return collect(config('llm.providers', []))
            ->filter(fn (array $provider): bool => (bool) ($provider['implemented'] ?? false))
            ->map(fn (array $provider, string $id): array => [
                'id' => $id,
                'label' => (string) ($provider['label'] ?? ucfirst($id)),
                'driver' => (string) ($provider['driver'] ?? $id),
                'models_refreshed_at' => $provider['models_refreshed_at'] ?? null,
            ])
            ->values()
            ->all();
    }

    public function defaultProvider(): string
    {
        $default = (string) config('llm.default_provider', 'anthropic');

        return $this->isImplementedProvider($default)
            ? $default
            : (string) ($this->implementedProviders()[0]['id'] ?? 'anthropic');
    }

    public function isImplementedProvider(string $provider): bool
    {
        return (bool) config("llm.providers.{$provider}.implemented", false);
    }

    public function modelOptions(string $provider, ?string $apiKey = null): array
    {
        if (! $this->isImplementedProvider($provider)) {
            return [];
        }

        $fetched = $this->catalog->models($provider, $apiKey);

        if (is_array($fetched) && $fetched !== []) {
            return $this->normalizeModels($fetched, $provider);
        }

        return [];
    }

    public function refreshModelOptions(string $provider, ?string $apiKey = null): array
    {
        if (! $this->isImplementedProvider($provider)) {
            return [];
        }

        $fetched = $this->catalog->refresh($provider, $apiKey);

        if (is_array($fetched) && $fetched !== []) {
            return $this->normalizeModels($fetched, $provider);
        }

        return [];
    }

    public function modelIds(string $provider, ?string $apiKey = null): array
    {
        return array_column($this->modelOptions($provider, $apiKey), 'id');
    }

    public function supportsModality(string $provider, string $modelId, string $modality, ?string $apiKey = null): bool
    {
        foreach ($this->modelOptions($provider, $apiKey) as $option) {
            if (($option['id'] ?? null) === $modelId) {
                return in_array($modality, (array) ($option['modalities'] ?? []), true);
            }
        }

        return in_array($modality, $this->capabilities->detect($provider, $modelId), true);
    }

    public function defaultModel(string $provider, string $stage, ?string $apiKey = null): string
    {
        $model = (string) config("llm.providers.{$provider}.models.{$stage}", '');
        $modelIds = $this->modelIds($provider, $apiKey);

        if ($model !== '' && in_array($model, $modelIds, true)) {
            return $model;
        }

        return (string) ($modelIds[0] ?? '');
    }

    private function normalizeModels(array $models, ?string $provider = null): array
    {
        return collect($models)
            ->map(function (mixed $model) use ($provider): array {
                if (is_string($model)) {
                    return [
                        'id' => $model,
                        'label' => $model,
                        'modalities' => $provider !== null ? $this->capabilities->detect($provider, $model) : ['text'],
                    ];
                }

                $id = (string) ($model['id'] ?? '');
                $modalities = $model['modalities'] ?? null;

                if (! is_array($modalities) || $modalities === []) {
                    $modalities = $provider !== null ? $this->capabilities->detect($provider, $id) : ['text'];
                }

                return [
                    'id' => $id,
                    'label' => (string) ($model['label'] ?? $model['id'] ?? ''),
                    'modalities' => array_values(array_filter(array_map('strval', $modalities))),
                ];
            })
            ->filter(fn (array $model): bool => $model['id'] !== '')
            ->unique('id')
            ->values()
            ->all();
    }
}
