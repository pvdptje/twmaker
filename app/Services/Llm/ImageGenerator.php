<?php

namespace App\Services\Llm;

use App\Models\Team;
use Illuminate\Support\Facades\Log;
use Prism\Prism\Facades\Prism;
use Throwable;

/**
 * Generates images on a dedicated image model (OpenAI gpt-image-1 by default),
 * decoupled from the design model the user selects for HTML edits. Returns raw
 * decoded image bytes; persisting them into the asset library is the caller's
 * job (see ProjectAssetLibrary::storeGeneratedImage()).
 */
class ImageGenerator
{
    public function __construct(private readonly TeamProviderCredentials $credentials) {}

    public function provider(): string
    {
        return (string) $this->config('provider', 'openai');
    }

    /**
     * Whether an image model can actually run for this team. Image generation is
     * gated on this so the UI can hide the control when no key is available.
     */
    public function isConfigured(?Team $team): bool
    {
        return $this->credentials->apiKey($team, $this->provider()) !== null;
    }

    public function generate(string $prompt, ?Team $team): GeneratedImageData
    {
        $prompt = trim($prompt);
        if ($prompt === '') {
            throw new ImageGenerationException('Describe the image you want to generate.');
        }

        $provider = $this->provider();
        $apiKey = $this->credentials->apiKey($team, $provider);
        if ($apiKey === null) {
            throw new ImageGenerationException('Image generation is not configured. Add an API key for '.$provider.' first.');
        }

        $model = (string) $this->config('model', 'gpt-image-1');

        try {
            $response = Prism::image()
                ->using($this->prismProvider($provider), $model, $this->providerConfig($provider, $apiKey))
                ->withPrompt($prompt)
                ->withProviderOptions($this->providerOptions())
                ->withClientOptions(['timeout' => (float) $this->config('timeout', 180)])
                ->generate();
        } catch (Throwable $exception) {
            Log::error('Image generation request failed.', [
                'provider' => $provider,
                'model' => $model,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            throw new ImageGenerationException('The image could not be generated. '.$exception->getMessage(), previous: $exception);
        }

        $image = $response->firstImage();
        $base64 = $image?->base64;

        if ($image === null || ! is_string($base64) || $base64 === '') {
            throw new ImageGenerationException('The image provider returned no image.');
        }

        $bytes = base64_decode($base64, true);
        if ($bytes === false || $bytes === '') {
            throw new ImageGenerationException('The generated image could not be decoded.');
        }

        return new GeneratedImageData(
            bytes: $bytes,
            mimeType: $this->resolveMime($image->mimeType),
            revisedPrompt: $image->revisedPrompt,
        );
    }

    private function prismProvider(string $provider): string
    {
        return (string) config("llm.providers.{$provider}.prism_provider", $provider);
    }

    /**
     * @return array<string, mixed>
     */
    private function providerConfig(string $provider, string $apiKey): array
    {
        $config = ['api_key' => $apiKey];

        $url = trim((string) config("llm.providers.{$provider}.url", ''));
        if ($url !== '') {
            $config['url'] = rtrim($url, '/');
        }

        return $config;
    }

    /**
     * @return array<string, mixed>
     */
    private function providerOptions(): array
    {
        return array_filter(
            [
                'size' => $this->config('size'),
                'quality' => $this->config('quality'),
                'output_format' => $this->config('output_format'),
            ],
            fn (mixed $value): bool => is_string($value) && $value !== '',
        );
    }

    private function resolveMime(?string $providerMime): string
    {
        if (is_string($providerMime) && $providerMime !== '') {
            return strtolower($providerMime);
        }

        return match (strtolower((string) $this->config('output_format', 'png'))) {
            'jpeg', 'jpg' => 'image/jpeg',
            'webp' => 'image/webp',
            default => 'image/png',
        };
    }

    private function config(string $key, mixed $default = null): mixed
    {
        return config("llm.image_generation.{$key}", $default);
    }
}
