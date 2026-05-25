<?php

namespace App\Livewire\Builder\ModelSelector;

use App\Services\Llm\LlmRegistry;
use App\Services\Llm\TeamProviderCredentials;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class ModelSelector extends Component
{
    public function render(): View
    {
        $providerOptions = $this->credentials()->configuredProviderOptions($this->credentials()->currentTeam());
        $choices = $this->modelChoices($providerOptions);

        return view()->file(__DIR__.'/model-selector.blade.php', [
            'choices' => $choices,
            'defaultValue' => (string) ($choices[0]['value'] ?? ''),
            'providerIds' => array_column($providerOptions, 'id'),
        ]);
    }

    public function choicesForApiKeys(array $apiKeys): array
    {
        return $this->modelChoices($this->credentials()->configuredProviderOptions($this->credentials()->currentTeam()));
    }

    public function choicesForConfiguredProviders(): array
    {
        return $this->modelChoices($this->credentials()->configuredProviderOptions($this->credentials()->currentTeam()));
    }

    private function modelChoices(array $providerOptions): array
    {
        return collect($providerOptions)
            ->flatMap(function (array $provider): array {
                $providerId = (string) $provider['id'];
                $providerLabel = (string) $provider['label'];
                $apiKey = $this->credentials()->apiKey($this->credentials()->currentTeam(), $providerId);
                $models = $this->registry()->modelOptions($providerId, $apiKey);

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

    private function registry(): LlmRegistry
    {
        return app(LlmRegistry::class);
    }

    private function credentials(): TeamProviderCredentials
    {
        return app(TeamProviderCredentials::class);
    }
}
