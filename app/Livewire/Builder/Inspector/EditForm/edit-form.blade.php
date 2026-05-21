<section
    class="border-b border-neutral-800 p-4"
    x-data="{
        provider: @entangle('provider').live,
        model: @entangle('model').live,
        apiKey: @entangle('apiKey'),
        storageKey(provider) { return `twmaker.apiKey.${provider}`; },
        loadKey() { this.apiKey = localStorage.getItem(this.storageKey(this.provider)) || ''; },
        saveKey() {
            if (!this.provider) return;
            if (this.apiKey) localStorage.setItem(this.storageKey(this.provider), this.apiKey);
            else localStorage.removeItem(this.storageKey(this.provider));
        },
    }"
    x-init="loadKey(); $watch('provider', () => loadKey()); $watch('apiKey', () => saveKey())"
>
    <div class="text-xs font-semibold uppercase tracking-normal text-neutral-500">Edit request</div>
    @if (count($selectedBlockIds) > 1)
        <div class="mt-2 rounded-md border border-cyan-500/40 bg-cyan-500/10 px-3 py-2 text-xs text-cyan-100">
            Multi edit: {{ count($selectedBlockIds) }} sections selected
        </div>
    @endif
    <textarea wire:model="instruction" rows="5" class="mt-3 w-full rounded-md border border-neutral-800 bg-neutral-950 px-3 py-2 text-sm text-white outline-none focus:border-cyan-400" placeholder="Describe the change for the selected section{{ count($selectedBlockIds) > 1 ? 's' : '' }}"></textarea>
    @error('instruction')
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
    <label class="mt-3 block text-xs font-medium text-neutral-400">
        API key
        <input wire:model.blur="apiKey" type="password" autocomplete="off" class="mt-1 w-full rounded-md border border-neutral-800 bg-neutral-950 px-3 py-2 text-sm text-white outline-none focus:border-cyan-400" placeholder="Stored locally for this provider">
    </label>
    @error('apiKey')
        <div class="mt-2 text-xs text-red-300">{{ $message }}</div>
    @enderror
    <button type="button" wire:click="refreshModels" class="mt-3 w-full rounded-md border border-neutral-700 px-3 py-2 text-sm font-semibold text-neutral-200 hover:border-neutral-500">Refresh models</button>
    @if ($modelCatalogStatus !== '')
        <div class="mt-2 text-xs text-neutral-500">{{ $modelCatalogStatus }}</div>
    @endif
    @error('selectedNodeId')
        <div class="mt-2 text-xs text-red-300">Select a section before applying an edit.</div>
    @enderror
    <button
        type="button"
        wire:click="applyEdit"
        wire:loading.attr="disabled"
        @disabled(! $selectedNodeId && count($selectedBlockIds) === 0)
        class="mt-3 w-full rounded-md bg-cyan-500 px-3 py-2 text-sm font-semibold text-neutral-950 hover:bg-cyan-400 disabled:bg-neutral-800 disabled:text-neutral-400"
    >
        {{ count($selectedBlockIds) > 1 ? 'Apply multi edit' : 'Apply edit' }}
    </button>
</section>
