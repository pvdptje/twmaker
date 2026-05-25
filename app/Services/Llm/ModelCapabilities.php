<?php

namespace App\Services\Llm;

class ModelCapabilities
{
    /**
     * Modality identifiers we currently surface in the UI.
     */
    public const MODALITY_TEXT = 'text';

    public const MODALITY_IMAGE = 'image';

    /**
     * Resolve the input modalities a given model accepts. The provider payload
     * (when available) takes precedence over id-based heuristics, since some
     * providers (OpenRouter, Gemini) report capabilities explicitly.
     *
     * @param  array<string, mixed>|null  $payload  Raw model entry from the provider API.
     * @return array<int, string>
     */
    public function detect(string $provider, string $modelId, ?array $payload = null): array
    {
        $modalities = [self::MODALITY_TEXT];

        $explicit = $this->explicitModalitiesFromPayload($provider, $payload);
        if ($explicit !== null) {
            return $this->normalize($explicit);
        }

        if ($this->matchesVisionPattern($provider, $modelId)) {
            $modalities[] = self::MODALITY_IMAGE;
        }

        return $this->normalize($modalities);
    }

    /**
     * @return array<int, string>|null
     */
    private function explicitModalitiesFromPayload(string $provider, ?array $payload): ?array
    {
        if ($payload === null) {
            return null;
        }

        if ($provider === 'openrouter') {
            $architecture = $payload['architecture'] ?? null;
            $inputs = is_array($architecture) ? ($architecture['input_modalities'] ?? null) : null;

            if (is_array($inputs) && $inputs !== []) {
                return array_values(array_filter(array_map('strval', $inputs)));
            }
        }

        if ($provider === 'gemini') {
            $methods = $payload['supportedGenerationMethods'] ?? null;
            if (is_array($methods) && in_array('generateContent', $methods, true)) {
                return $this->matchesVisionPattern($provider, (string) ($payload['name'] ?? ''))
                    ? [self::MODALITY_TEXT, self::MODALITY_IMAGE]
                    : [self::MODALITY_TEXT];
            }
        }

        return null;
    }

    private function matchesVisionPattern(string $provider, string $modelId): bool
    {
        $id = strtolower($modelId);

        foreach ($this->visionPatterns($provider) as $pattern) {
            if (preg_match($pattern, $id) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    private function visionPatterns(string $provider): array
    {
        return match ($provider) {
            'anthropic' => [
                '/^claude-(3|4|5|sonnet|opus|haiku)/i',
            ],
            'openai' => [
                '/^gpt-4o/i',
                '/^gpt-4\.1/i',
                '/^gpt-4-turbo/i',
                '/^gpt-4-vision/i',
                '/^gpt-5/i',
                '/^chatgpt-4o/i',
                '/^o1(?!-mini)/i',
                '/^o3/i',
                '/^o4/i',
            ],
            'gemini' => [
                '/^models\/gemini-(1\.5|2|2\.5|3)/i',
                '/^gemini-(1\.5|2|2\.5|3)/i',
                '/^gemini-pro-vision/i',
            ],
            'xai' => [
                '/^grok-(2-)?vision/i',
                '/^grok-(3|4|5)/i',
            ],
            'mistral' => [
                '/^pixtral/i',
                '/^mistral-medium-(2|3)/i',
                '/^mistral-large-(2|3)/i',
            ],
            'groq' => [
                '/vision/i',
                '/^llama-(3\.2|4)/i',
            ],
            'ollama' => [
                '/^(llava|bakllava|moondream|minicpm-v|qwen2\.5vl|qwen2-vl)/i',
                '/-vision\b/i',
                '/^llama3\.2-vision/i',
                '/^gemma3/i',
            ],
            'openrouter' => [
                // OpenRouter exposes explicit modalities; this fallback only fires
                // for cached entries written before that field was captured.
                '/vision/i',
                '/^(anthropic\/claude-(3|4))/i',
                '/^(openai\/gpt-4o|openai\/gpt-4\.1|openai\/gpt-5|openai\/o3|openai\/o4)/i',
                '/^(google\/gemini-(1\.5|2|2\.5))/i',
                '/^(x-ai\/grok-(2-vision|3|4))/i',
            ],
            default => [],
        };
    }

    /**
     * @param  array<int, string>  $modalities
     * @return array<int, string>
     */
    private function normalize(array $modalities): array
    {
        $allowed = [self::MODALITY_TEXT, self::MODALITY_IMAGE];

        $filtered = array_values(array_unique(array_filter(
            array_map('strtolower', $modalities),
            fn (string $modality): bool => in_array($modality, $allowed, true),
        )));

        if (! in_array(self::MODALITY_TEXT, $filtered, true)) {
            array_unshift($filtered, self::MODALITY_TEXT);
        }

        return array_values($filtered);
    }
}
