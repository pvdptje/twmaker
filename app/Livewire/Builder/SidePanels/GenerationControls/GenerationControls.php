<?php

namespace App\Livewire\Builder\SidePanels\GenerationControls;

use App\Jobs\EnhanceDocumentJob;
use App\Jobs\GeneratePageJob;
use App\Models\Page;
use App\Services\Generation\GenerationEventRecorder;
use App\Services\Llm\ImageAttachments;
use App\Services\Llm\LlmRegistry;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class GenerationControls extends Component
{
    public Page $page;

    public string $prompt = '';

    public string $provider = '';

    public string $model = '';

    public string $apiKey = '';

    public string $modelCatalogStatus = '';

    public string $customEnhancementPrompt = '';

    /**
     * @var array<int, array{base64: string, mime_type: string}>
     */
    public array $images = [];

    public function mount(Page $page): void
    {
        $this->prompt = $page->prompt;
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

    public function generate(): void
    {
        $this->validate([
            'prompt' => ['required', 'string', 'min:3', 'max:5000'],
            'provider' => ['required', 'string', 'in:'.implode(',', $this->providerIds())],
            'model' => ['required', 'string', 'in:'.implode(',', $this->modelIds())],
            'apiKey' => ['nullable', 'string', 'max:500'],
        ]);

        if ($this->images !== [] && ! $this->registry()->supportsModality($this->provider, $this->model, 'image', $this->normalizedApiKey())) {
            $this->addError('prompt', 'The selected model does not accept image input. Pick a vision-capable model or remove the attachments.');

            return;
        }

        $this->page->forceFill([
            'prompt' => $this->prompt,
            'status' => 'generating',
        ])->save();

        $this->dispatch('generation-started', pageId: $this->page->id, stage: 'section_generator');

        GeneratePageJob::dispatch($this->page->id, $this->provider, $this->model, $this->normalizedApiKey(), $this->images);
        $this->images = [];

        $this->dispatch('generation-finished', pageId: $this->page->id, status: $this->page->refresh()->status);
    }

    /**
     * @param  array<int, array{base64?: string, mime_type?: string}>|null  $attachments
     */
    public function generateWithSelection(string $provider, string $model, ?string $apiKey = null, ?array $attachments = null): void
    {
        $this->provider = $provider;
        $this->model = $model;
        $this->apiKey = (string) $apiKey;
        $this->images = app(ImageAttachments::class)->normalize($attachments);
        $this->storeProvider();
        $this->storeModel();

        $this->generate();
    }

    public function runEnhancementWithSelection(string $enhancement, string $provider, string $model, ?string $apiKey = null, ?string $customPrompt = null): void
    {
        $this->provider = $provider;
        $this->model = $model;
        $this->apiKey = (string) $apiKey;
        $this->customEnhancementPrompt = (string) $customPrompt;
        $this->storeProvider();
        $this->storeModel();

        $this->runEnhancement($enhancement);
    }

    public function runEnhancement(string $enhancement): void
    {
        $this->validate([
            'provider' => ['required', 'string', 'in:'.implode(',', $this->providerIds())],
            'model' => ['required', 'string', 'in:'.implode(',', $this->modelIds())],
            'apiKey' => ['nullable', 'string', 'max:500'],
        ]);

        if (trim((string) ($this->page->html_source ?? '')) === '') {
            $this->addError('prompt', 'Generate a page before refining editability.');

            return;
        }

        [$instruction, $summary] = $this->enhancementPrompt($enhancement);

        if ($instruction === '') {
            $this->addError('customEnhancementPrompt', 'Enter an enhancement prompt.');

            return;
        }

        app(GenerationEventRecorder::class)->record(
            $this->page,
            'enhance_requested',
            'document_enhancer',
            'info',
            $summary,
            payload: [
                'instruction' => $instruction,
                'summary' => $summary,
            ],
        );

        $this->page->forceFill(['status' => 'generating'])->save();
        $this->dispatch('generation-started', pageId: $this->page->id, stage: 'document_enhancer');

        EnhanceDocumentJob::dispatch($this->page->id, $instruction, $summary, $this->provider, $this->model, $this->normalizedApiKey());

        $this->dispatch('generation-finished', pageId: $this->page->id, status: $this->page->refresh()->status);
    }

    public function render(): View
    {
        return view()->file(__DIR__.'/generation-controls.blade.php');
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
        return $this->registry()->defaultModel($this->provider, 'section_generator', $this->normalizedApiKey());
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function enhancementPrompt(string $enhancement): array
    {
        return match ($enhancement) {
            'editability' => [
                'Add more granular editable tw:block regions around repeated meaningful content such as testimonial cards, feature cards, pricing cards, FAQ rows, stats, logos, gallery items, and CTA groups. When splitting a coarse parent into child blocks, convert the parent tw:block markers into tw:group wrapper markers so the parent container remains selectable while tw:block markers are never nested.',
                'Refined editable block markers.',
            ],
            'color_scheme' => [
                'Refresh the global visual color scheme by changing Tailwind background, text, border, ring, and gradient color utilities consistently across the whole document. Preserve layout, copy, block boundaries, contrast, and readability.',
                'Refreshed global color scheme.',
            ],
            'custom' => [
                trim($this->customEnhancementPrompt),
                'Custom enhancement.',
            ],
            default => ['', 'Enhancement.'],
        };
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
        $provider = session('builder.primary.provider');

        return is_string($provider) && $this->registry()->isImplementedProvider($provider) ? $provider : null;
    }

    private function storedModel(string $provider): ?string
    {
        $model = session("builder.primary.models.{$provider}");

        return is_string($model) && in_array($model, $this->registry()->modelIds($provider, $this->normalizedApiKey()), true)
            ? $model
            : null;
    }

    private function storeProvider(): void
    {
        if ($this->provider !== '') {
            session(['builder.primary.provider' => $this->provider]);
        }
    }

    private function storeModel(): void
    {
        if ($this->provider !== '' && $this->model !== '') {
            session(["builder.primary.models.{$this->provider}" => $this->model]);
        }
    }
}
