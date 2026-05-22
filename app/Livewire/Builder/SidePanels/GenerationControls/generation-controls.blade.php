<section
    class="p-4"
    x-data="{
        provider: @entangle('provider').live,
        model: @entangle('model').live,
        apiKey: @entangle('apiKey'),
        storageKey(provider) { return `twmaker.apiKey.${provider}`; },
        defaultKey(field) { return `twmaker.llmDefaults.primary.${field}`; },
        selectionKey(field) { return `twmaker.builder.primary.${field}`; },
        loadKey() { this.apiKey = localStorage.getItem(this.storageKey(this.provider)) || ''; },
        loadDefaults() {
            this.provider = localStorage.getItem(this.selectionKey('provider')) || localStorage.getItem(this.defaultKey('provider')) || this.provider;
            this.$nextTick(() => {
                this.model = localStorage.getItem(this.selectionKey(`model.${this.provider}`)) || localStorage.getItem(this.defaultKey('model')) || this.model;
                this.loadKey();
            });
        },
        saveSelection() {
            if (!this.provider) return;
            localStorage.setItem(this.selectionKey('provider'), this.provider);
            if (this.model) localStorage.setItem(this.selectionKey(`model.${this.provider}`), this.model);
        },
    }"
    x-init="loadDefaults(); $watch('provider', () => { loadKey(); saveSelection() }); $watch('model', () => saveSelection())"
>
    <div class="flex items-center justify-between gap-3">
        <div class="text-xs font-semibold uppercase tracking-normal text-neutral-500">Generation</div>
        <a href="{{ route('setup.llm') }}" wire:navigate class="text-xs font-medium text-cyan-300 hover:text-cyan-200">Setup</a>
    </div>
    <textarea wire:model="prompt" rows="4" class="mt-3 w-full rounded-md border border-neutral-800 bg-neutral-950 px-3 py-2 text-sm text-white outline-none focus:border-cyan-400"></textarea>
    @error('prompt')
        <div class="mt-2 text-xs text-red-300">{{ $message }}</div>
    @enderror
    <label class="mt-3 block text-xs font-medium text-neutral-400">
        Provider
        <select wire:model.live="provider" class="mt-1 w-full rounded-md border border-neutral-800 bg-neutral-950 px-3 py-2 text-sm text-white outline-none focus:border-cyan-400">
            @foreach ($providerOptions as $providerOption)
                <option value="{{ $providerOption['id'] }}">{{ $providerOption['label'] }}</option>
            @endforeach
        </select>
    </label>
    @error('provider')
        <div class="mt-2 text-xs text-red-300">{{ $message }}</div>
    @enderror
    <label class="mt-3 block text-xs font-medium text-neutral-400">
        Model
        <select wire:model.live="model" class="mt-1 w-full rounded-md border border-neutral-800 bg-neutral-950 px-3 py-2 text-sm text-white outline-none focus:border-cyan-400">
            @foreach ($modelOptions as $modelOption)
                <option value="{{ $modelOption['id'] }}">{{ $modelOption['label'] }} ({{ $modelOption['id'] }})</option>
            @endforeach
        </select>
    </label>
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
        x-on:click="saveSelection(); $wire.generateWithSelection(provider, model, apiKey)"
        class="mt-3 w-full rounded-md bg-cyan-500 px-3 py-2 text-sm font-semibold text-neutral-950 hover:bg-cyan-400"
    >
        Generate
    </button>
</section>
