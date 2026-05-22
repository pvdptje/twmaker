<?php

namespace App\Services\Llm;

class LlmRegistry
{
    public function __construct(private readonly ProviderModelCatalog $catalog) {}

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
            return $this->normalizeModels(array_merge($fetched, $this->fallbackModelOptions($provider)));
        }

        return $this->fallbackModelOptions($provider);
    }

    public function refreshModelOptions(string $provider, ?string $apiKey = null): array
    {
        if (! $this->isImplementedProvider($provider)) {
            return [];
        }

        $fetched = $this->catalog->refresh($provider, $apiKey);

        if (is_array($fetched) && $fetched !== []) {
            return $this->normalizeModels(array_merge($fetched, $this->fallbackModelOptions($provider)));
        }

        return $this->fallbackModelOptions($provider);
    }

    public function modelIds(string $provider, ?string $apiKey = null): array
    {
        return array_column($this->modelOptions($provider, $apiKey), 'id');
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

    private function fallbackModelOptions(string $provider): array
    {
        $configured = config("llm.providers.{$provider}.available_models", []);

        return $this->normalizeModels($configured);
    }

    private function normalizeModels(array $models): array
    {
        return collect($models)
            ->map(function (mixed $model): array {
                if (is_string($model)) {
                    return ['id' => $model, 'label' => $model];
                }

                return [
                    'id' => (string) ($model['id'] ?? ''),
                    'label' => (string) ($model['label'] ?? $model['id'] ?? ''),
                ];
            })
            ->filter(fn (array $model): bool => $model['id'] !== '')
            ->unique('id')
            ->values()
            ->all();
    }
}
