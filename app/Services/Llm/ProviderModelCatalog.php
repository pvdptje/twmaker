<?php

namespace App\Services\Llm;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProviderModelCatalog
{
    public function __construct(private readonly ModelCapabilities $capabilities = new ModelCapabilities) {}

    public function models(string $provider, ?string $apiKey = null): ?array
    {
        $apiKey = $this->apiKey($provider, $apiKey);

        if ($apiKey === null) {
            if (! $this->canFetchWithoutApiKey($provider)) {
                return null;
            }

            $apiKey = '';
        }

        return Cache::get($this->cacheKey($provider, $apiKey));
    }

    public function refresh(string $provider, ?string $apiKey = null): ?array
    {
        $apiKey = $this->apiKey($provider, $apiKey);

        if ($apiKey === null) {
            if (! $this->canFetchWithoutApiKey($provider)) {
                return null;
            }

            $apiKey = '';
        }

        $models = $this->fetch($provider, $apiKey);

        if ($models !== null) {
            Cache::put(
                $this->cacheKey($provider, $apiKey),
                $models,
                now()->addSeconds((int) config('llm.model_cache_ttl_seconds', 86400)),
            );
        }

        return $models;
    }

    public function forgetModel(string $provider, ?string $apiKey, string $model): void
    {
        $apiKey = $this->apiKey($provider, $apiKey);

        if ($apiKey === null) {
            if (! $this->canFetchWithoutApiKey($provider)) {
                return;
            }

            $apiKey = '';
        }

        $cacheKey = $this->cacheKey($provider, $apiKey);
        $models = Cache::get($cacheKey);

        if (! is_array($models)) {
            return;
        }

        $models = array_values(array_filter(
            $models,
            fn (mixed $candidate): bool => (string) ($candidate['id'] ?? $candidate) !== $model,
        ));

        if ($models === []) {
            Cache::forget($cacheKey);

            return;
        }

        Cache::put(
            $cacheKey,
            $models,
            now()->addSeconds((int) config('llm.model_cache_ttl_seconds', 86400)),
        );
    }

    private function fetch(string $provider, string $apiKey): ?array
    {
        return match (config("llm.providers.{$provider}.prism_provider", config("llm.providers.{$provider}.driver"))) {
            'anthropic' => $this->fetchAnthropic($apiKey),
            'deepseek', 'openai', 'openrouter', 'mistral', 'groq', 'xai' => $this->fetchOpenAiCompatible($provider, $apiKey),
            'gemini' => $this->fetchGemini($provider, $apiKey),
            'ollama' => $this->fetchOllama($provider),
            default => null,
        };
    }

    private function fetchAnthropic(string $apiKey): ?array
    {
        try {
            $models = [];
            $afterId = null;

            for ($page = 0; $page < 10; $page++) {
                $response = Http::withHeaders([
                    'anthropic-version' => '2023-06-01',
                    'x-api-key' => $apiKey,
                ])
                    ->timeout(15)
                    ->get('https://api.anthropic.com/v1/models', array_filter([
                        'after_id' => $afterId,
                        'limit' => 100,
                    ]));

                if (! $response->successful()) {
                    Log::warning('Failed to fetch Anthropic models.', [
                        'status' => $response->status(),
                    ]);

                    return null;
                }

                $body = $response->json();

                foreach ($body['data'] ?? [] as $model) {
                    $id = (string) ($model['id'] ?? '');

                    if ($id === '') {
                        continue;
                    }

                    $models[] = [
                        'id' => $id,
                        'label' => (string) ($model['display_name'] ?? $id),
                        'modalities' => $this->capabilities->detect('anthropic', $id, $model),
                    ];
                }

                if (! (bool) ($body['has_more'] ?? false)) {
                    break;
                }

                $afterId = $body['last_id'] ?? null;

                if (! is_string($afterId) || $afterId === '') {
                    break;
                }
            }

            return $models === [] ? null : $models;
        } catch (Throwable $exception) {
            Log::warning('Failed to fetch Anthropic models.', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    private function fetchOpenAiCompatible(string $provider, string $apiKey): ?array
    {
        try {
            $baseUrl = rtrim((string) config("llm.providers.{$provider}.url", config("llm.providers.{$provider}.base_url", '')), '/');

            if ($baseUrl === '') {
                return null;
            }

            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->timeout(15)
                ->get($baseUrl.'/models');

            if (! $response->successful()) {
                Log::warning('Failed to fetch provider models.', [
                    'provider' => $provider,
                    'status' => $response->status(),
                ]);

                return null;
            }

            $models = [];
            foreach ($response->json('data', []) as $model) {
                $id = (string) ($model['id'] ?? '');

                if ($id === '') {
                    continue;
                }

                $models[] = [
                    'id' => $id,
                    'label' => $this->labelFromModelId($id),
                    'modalities' => $this->capabilities->detect($provider, $id, $model),
                ];
            }

            return $models === [] ? null : $models;
        } catch (Throwable $exception) {
            Log::warning('Failed to fetch provider models.', [
                'provider' => $provider,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    private function fetchGemini(string $provider, string $apiKey): ?array
    {
        try {
            $baseUrl = rtrim((string) config("llm.providers.{$provider}.url", 'https://generativelanguage.googleapis.com/v1beta/models'), '/');
            $response = Http::acceptJson()
                ->timeout(15)
                ->get($baseUrl, ['key' => $apiKey]);

            if (! $response->successful()) {
                Log::warning('Failed to fetch Gemini models.', [
                    'status' => $response->status(),
                ]);

                return null;
            }

            $models = [];
            foreach ($response->json('models', []) as $model) {
                $name = (string) ($model['name'] ?? '');
                $id = str($name)->afterLast('/')->toString();

                if ($id === '') {
                    continue;
                }

                $models[] = [
                    'id' => $id,
                    'label' => (string) ($model['displayName'] ?? $this->labelFromModelId($id)),
                    'modalities' => $this->capabilities->detect('gemini', $id, $model),
                ];
            }

            return $models === [] ? null : $models;
        } catch (Throwable $exception) {
            Log::warning('Failed to fetch Gemini models.', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    private function fetchOllama(string $provider): ?array
    {
        try {
            $baseUrl = rtrim((string) config("llm.providers.{$provider}.url", 'http://localhost:11434'), '/');
            $response = Http::acceptJson()
                ->timeout(15)
                ->get($baseUrl.'/api/tags');

            if (! $response->successful()) {
                Log::warning('Failed to fetch Ollama models.', [
                    'status' => $response->status(),
                ]);

                return null;
            }

            $models = [];
            foreach ($response->json('models', []) as $model) {
                $id = (string) ($model['name'] ?? '');

                if ($id === '') {
                    continue;
                }

                $models[] = [
                    'id' => $id,
                    'label' => $this->labelFromModelId($id),
                    'modalities' => $this->capabilities->detect('ollama', $id, $model),
                ];
            }

            return $models === [] ? null : $models;
        } catch (Throwable $exception) {
            Log::warning('Failed to fetch Ollama models.', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    private function labelFromModelId(string $id): string
    {
        return str($id)
            ->replace(['-', '_'], ' ')
            ->title()
            ->replace('Deepseek', 'DeepSeek')
            ->replace('Openai', 'OpenAI')
            ->replace('Gpt', 'GPT')
            ->replace('Xai', 'xAI')
            ->toString();
    }

    private function apiKey(string $provider, ?string $apiKey): ?string
    {
        $apiKey = trim((string) $apiKey);

        if ($apiKey !== '') {
            return $apiKey;
        }

        $configured = trim((string) config("llm.providers.{$provider}.api_key"));

        return $configured === '' ? null : $configured;
    }

    private function cacheKey(string $provider, string $apiKey): string
    {
        return "llm:models:{$provider}:".hash('sha256', $apiKey);
    }

    private function canFetchWithoutApiKey(string $provider): bool
    {
        return ! (bool) config("llm.providers.{$provider}.requires_api_key", true);
    }
}
