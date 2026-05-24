<section
    class="p-4"
    x-data="{
        provider: @js($provider),
        model: @js($model),
        apiKey: '',
        sharedKey: 'twmaker.builder.modelSelection',
        storageKey(provider) { return `twmaker.apiKey.${provider}`; },
        defaultKey(field) { return `twmaker.llmDefaults.primary.${field}`; },
        selectionKey(field) { return `twmaker.builder.primary.${field}`; },
        loadSelection() {
            try {
                const stored = JSON.parse(localStorage.getItem(this.sharedKey) || 'null');
                if (stored?.provider && stored?.model) {
                    this.provider = stored.provider;
                    this.model = stored.model;
                } else {
                    this.provider = localStorage.getItem(this.selectionKey('provider')) || localStorage.getItem(this.defaultKey('provider')) || this.provider;
                    this.model = this.provider ? (localStorage.getItem(this.selectionKey(`model.${this.provider}`)) || localStorage.getItem(this.defaultKey('model')) || this.model) : this.model;
                }
            } catch (error) {}

            this.apiKey = this.provider ? (localStorage.getItem(this.storageKey(this.provider)) || '') : '';
        },
        updateSelection(event) {
            this.provider = event.detail?.provider || this.provider;
            this.model = event.detail?.model || this.model;
            this.apiKey = event.detail?.apiKey || '';
        },
        startGenerate() {
            this.loadSelection();
            this.$wire.generateWithSelection(this.provider, this.model, this.apiKey);
        },
    }"
    x-init="loadSelection()"
    x-on:builder-model-selection-changed.window="updateSelection($event)"
>
    <div class="flex items-center justify-between gap-3">
        <div class="text-xs font-semibold uppercase tracking-normal text-neutral-500">Generation</div>
    </div>
    <textarea wire:model="prompt" rows="4" class="mt-3 w-full rounded-md border border-neutral-800 bg-neutral-950 px-3 py-2 text-sm text-white outline-none focus:border-cyan-400"></textarea>
    @error('prompt')
        <div class="mt-2 text-xs text-red-300">{{ $message }}</div>
    @enderror
    @error('provider')
        <div class="mt-2 text-xs text-red-300">{{ $message }}</div>
    @enderror
    @error('model')
        <div class="mt-2 text-xs text-red-300">{{ $message }}</div>
    @enderror
    <input type="hidden" wire:model="apiKey">
    @error('apiKey')
        <div class="mt-2 text-xs text-red-300">{{ $message }}</div>
    @enderror
    @if ($modelCatalogStatus !== '')
        <div class="mt-2 text-xs text-neutral-500">{{ $modelCatalogStatus }}</div>
    @endif
    <button
        type="button"
        x-on:click="startGenerate()"
        wire:loading.attr="disabled"
        wire:target="generateWithSelection,generate"
        class="mt-3 w-full rounded-md bg-cyan-500 px-3 py-2 text-sm font-semibold text-neutral-950 hover:bg-cyan-400"
    >
        Generate
    </button>
</section>
