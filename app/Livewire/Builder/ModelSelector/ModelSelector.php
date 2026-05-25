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
        ]);
    }

    private function modelChoices(array $providerOptions): array
    {
        return collect($providerOptions)
            ->flatMap(function (array $provider): array {
                $providerId = (string) $provider['id'];
                $providerLabel = (string) $provider['label'];

                return collect($this->registry()->modelOptions($providerId))
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
}
