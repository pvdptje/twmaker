<?php

namespace App\Livewire\Builder\ModelSelector;

use App\Services\Llm\LlmRegistry;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class ModelSelector extends Component
{
    public function render(): View
    {
        $providerOptions = $this->registry()->implementedProviders();
        $choices = $this->modelChoices($providerOptions);

        return view()->file(__DIR__.'/model-selector.blade.php', [
            'choices' => $choices,
            'defaultValue' => (string) ($choices[0]['value'] ?? ''),
            'providerIds' => array_column($providerOptions, 'id'),
        ]);
    }

    public function choicesForApiKeys(array $apiKeys): array
    {
        return $this->modelChoices(
            $this->registry()->implementedProviders(),
            $this->normalizedApiKeys($apiKeys),
        );
    }

    private function modelChoices(array $providerOptions, array $apiKeys = []): array
    {
        return collect($providerOptions)
            ->flatMap(function (array $provider) use ($apiKeys): array {
                $providerId = (string) $provider['id'];
                $providerLabel = (string) $provider['label'];
                $apiKey = $apiKeys[$providerId] ?? null;
                $models = $this->registry()->modelOptions($providerId, $apiKey);

                if ($models === [] && $apiKey !== null) {
                    $models = $this->registry()->modelOptions($providerId);
                }

                return collect($models)
                    ->map(function (array $model) use ($providerId, $providerLabel): array {
                        $modalities = (array) ($model['modalities'] ?? ['text']);
                        $supportsVision = in_array('image', $modalities, true);

                        return [
                            'value' => $providerId.'|'.$model['id'],
                            'provider' => $providerId,
                            'providerLabel' => $providerLabel,
                            'model' => (string) $model['id'],
                            'modalities' => array_values($modalities),
                            'label' => $providerLabel.' - '.$model['label'].' ('.$model['id'].')'
                                .($supportsVision ? ' [vision]' : ''),
                        ];
                    })
                    ->all();
            })
            ->values()
            ->all();
    }

    private function normalizedApiKeys(array $apiKeys): array
    {
        return collect($apiKeys)
            ->mapWithKeys(function (mixed $apiKey, mixed $provider): array {
                $provider = is_string($provider) ? trim($provider) : '';
                $apiKey = is_string($apiKey) ? trim($apiKey) : '';

                return $provider !== '' && $apiKey !== '' ? [$provider => $apiKey] : [];
            })
            ->all();
    }

    private function registry(): LlmRegistry
    {
        return app(LlmRegistry::class);
    }
}
