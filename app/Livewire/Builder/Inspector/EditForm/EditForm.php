<?php

namespace App\Livewire\Builder\Inspector\EditForm;

use App\Jobs\TargetedEditJob;
use App\Models\Page;
use App\Services\Generation\GenerationEventRecorder;
use App\Services\Llm\ImageAttachments;
use App\Services\Llm\LlmRegistry;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Reactive;
use Livewire\Component;

class EditForm extends Component
{
    public Page $page;

    #[Reactive]
    public ?string $selectedNodeId = null;

    #[Reactive]
    public array $selectedBlockIds = [];

    public string $instruction = '';

    public string $provider = '';

    public string $model = '';

    public string $apiKey = '';

    public string $modelCatalogStatus = '';

    /**
     * @var array<int, array{base64: string, mime_type: string}>
     */
    public array $images = [];

    public function mount(): void
    {
        $this->provider = $this->storedProvider() ?? $this->registry()->defaultProvider();
        $this->model = $this->storedModel($this->provider) ?? $this->defaultModel();
    }

    public function updatedProvider(): void
    {
        $this->storeProvider();
        $this->model = $this->storedModel($this->provider) ?? $this->defaultModel();
        $this->modelCatalogStatus = '';
    }

    public function updatedModel(): void
    {
        $this->storeModel();
    }

    public function updatedApiKey(): void
    {
        $this->ensureSelectedModelIsAvailable();
        $this->modelCatalogStatus = '';
    }

    public function refreshModels(): void
    {
        if (! $this->hasModelFetchKey()) {
            $this->modelCatalogStatus = 'Add an API key to refresh provider models.';

            return;
        }

        $models = $this->registry()->refreshModelOptions($this->provider, $this->normalizedApiKey());
        $this->ensureSelectedModelIsAvailable($models);

        $this->modelCatalogStatus = count($models).' models refreshed.';
    }

    public function applyEdit(): void
    {
        $this->validate([
            'selectedNodeId' => ['nullable', 'string'],
            'selectedBlockIds' => ['array'],
            'selectedBlockIds.*' => ['string'],
            'instruction' => ['required', 'string', 'min:3', 'max:5000'],
            'provider' => ['required', 'string', 'in:'.implode(',', $this->providerIds())],
            'model' => ['required', 'string', 'in:'.implode(',', $this->modelIds())],
            'apiKey' => ['nullable', 'string', 'max:500'],
        ]);

        $targetIds = $this->targetIds();
        if ($targetIds === []) {
            $this->addError('selectedNodeId', 'Select one or more sections before applying an edit.');

            return;
        }

        if ($this->images !== [] && ! $this->registry()->supportsModality($this->provider, $this->model, 'image', $this->normalizedApiKey())) {
            $this->addError('instruction', 'The selected model does not accept image input. Pick a vision-capable model or remove the attachments.');

            return;
        }

        app(GenerationEventRecorder::class)->record(
            $this->page,
            'edit_requested',
            'targeted_edit',
            'info',
            count($targetIds) > 1 ? 'Editing selected block range.' : 'Editing selected block.',
            implode(',', $targetIds),
            [
                'instruction' => $this->instruction,
                'target_ids' => $targetIds,
                'reference_images' => count($this->images),
            ],
        );

        $this->page->forceFill(['status' => 'generating'])->save();
        $this->dispatch('generation-started', pageId: $this->page->id, stage: 'targeted_edit');

        TargetedEditJob::dispatch($this->page->id, count($targetIds) === 1 ? $targetIds[0] : $targetIds, $this->instruction, $this->provider, $this->model, $this->normalizedApiKey(), $this->images);
        $this->instruction = '';
        $this->images = [];
    }

    /**
     * @param  array<int, array{base64?: string, mime_type?: string}>|null  $attachments
     */
    public function applyEditWithSelection(string $provider, string $model, ?string $apiKey = null, ?array $attachments = null): void
    {
        $this->provider = $provider;
        $this->model = $model;
        $this->apiKey = (string) $apiKey;
        $this->images = app(ImageAttachments::class)->normalize($attachments);
        $this->storeProvider();
        $this->storeModel();

        $this->applyEdit();
    }

    public function render(): View
    {
        return view()->file(__DIR__.'/edit-form.blade.php');
    }

    private function providerOptions(): array
    {
        return $this->registry()->implementedProviders();
    }

    private function providerIds(): array
    {
        return array_column($this->providerOptions(), 'id');
    }

    private function modelOptions(): array
    {
        return $this->registry()->modelOptions($this->provider, $this->normalizedApiKey());
    }

    private function modelIds(): array
    {
        return array_column($this->modelOptions(), 'id');
    }

    private function defaultModel(): string
    {
        return $this->registry()->defaultModel($this->provider, 'targeted_edit', $this->normalizedApiKey());
    }

    private function normalizedApiKey(): ?string
    {
        $apiKey = trim($this->apiKey);

        return $apiKey === '' ? null : $apiKey;
    }

    private function registry(): LlmRegistry
    {
        return app(LlmRegistry::class);
    }

    private function ensureSelectedModelIsAvailable(?array $models = null): void
    {
        $modelIds = array_column($models ?? $this->modelOptions(), 'id');

        if (! in_array($this->model, $modelIds, true)) {
            $this->model = $this->defaultModel();
        }
    }

    private function hasModelFetchKey(): bool
    {
        if (! (bool) config("llm.providers.{$this->provider}.requires_api_key", true)) {
            return true;
        }

        return $this->normalizedApiKey() !== null
            || trim((string) config("llm.providers.{$this->provider}.api_key")) !== '';
    }

    private function storedProvider(): ?string
    {
        $provider = session('builder.editing.provider');

        return is_string($provider) && $this->registry()->isImplementedProvider($provider) ? $provider : null;
    }

    private function storedModel(string $provider): ?string
    {
        $model = session("builder.editing.models.{$provider}");

        return is_string($model) && in_array($model, $this->registry()->modelIds($provider, $this->normalizedApiKey()), true)
            ? $model
            : null;
    }

    private function storeProvider(): void
    {
        if ($this->provider !== '') {
            session(['builder.editing.provider' => $this->provider]);
        }
    }

    private function storeModel(): void
    {
        if ($this->provider !== '' && $this->model !== '') {
            session(["builder.editing.models.{$this->provider}" => $this->model]);
        }
    }

    /**
     * @return array<int, string>
     */
    private function targetIds(): array
    {
        $selectedBlockIds = array_values(array_unique(array_filter(
            $this->selectedBlockIds,
            fn (mixed $id): bool => is_string($id) && $id !== '',
        )));

        if ($selectedBlockIds !== []) {
            return $selectedBlockIds;
        }

        return is_string($this->selectedNodeId) && $this->selectedNodeId !== ''
            ? [$this->selectedNodeId]
            : [];
    }
}
