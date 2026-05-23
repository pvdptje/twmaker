<section
    class="border-b border-neutral-800 p-4"
    x-data="{
        provider: @js($provider),
        model: @js($model),
        apiKey: '',
        modelOptionsByProvider: @js($modelOptionsByProvider),
        storageKey(provider) { return `twmaker.apiKey.${provider}`; },
        defaultKey(field) { return `twmaker.llmDefaults.editing.${field}`; },
        selectionKey(field) { return `twmaker.builder.editing.${field}`; },
        loadKey() { this.apiKey = localStorage.getItem(this.storageKey(this.provider)) || ''; },
        modelOptions() { return this.modelOptionsByProvider[this.provider] || []; },
        ensureModel() {
            if (this.modelOptions().some((option) => option.id === this.model)) return;
            this.model = this.modelOptions()[0]?.id || '';
        },
        loadDefaults() {
            this.provider = localStorage.getItem(this.selectionKey('provider')) || localStorage.getItem(this.defaultKey('provider')) || this.provider;
            this.$nextTick(() => {
                this.model = localStorage.getItem(this.selectionKey(`model.${this.provider}`)) || localStorage.getItem(this.defaultKey('model')) || this.model;
                this.ensureModel();
                this.loadKey();
            });
        },
        saveSelection() {
            if (!this.provider) return;
            localStorage.setItem(this.selectionKey('provider'), this.provider);
            if (this.model) localStorage.setItem(this.selectionKey(`model.${this.provider}`), this.model);
        },
        editRunning: false,
        startEdit() {
            this.saveSelection();
            this.$wire.applyEditWithSelection(this.provider, this.model, this.apiKey);
        },
        beginEdit(event) {
            if (event.detail?.pageId && event.detail.pageId !== @js($page->id)) return;
            if (event.detail?.stage !== 'targeted_edit') return;
            this.editRunning = true;
        },
        finishEdit(event) {
            if (event.detail?.pageId && event.detail.pageId !== @js($page->id)) return;
            this.editRunning = false;
        },
    }"
    x-init="loadDefaults(); $watch('provider', () => { ensureModel(); loadKey(); saveSelection() }); $watch('model', () => saveSelection())"
    x-on:generation-started.window="beginEdit($event)"
    x-on:generation-finished.window="finishEdit($event)"
>
    <div class="flex items-center justify-between gap-3">
        <div class="text-xs font-semibold uppercase tracking-normal text-neutral-500">Edit request</div>
        <a href="{{ route('setup.llm') }}" wire:navigate class="text-xs font-medium text-cyan-300 hover:text-cyan-200">Setup</a>
    </div>
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
        <select x-model="provider" class="mt-1 w-full rounded-md border border-neutral-800 bg-neutral-950 px-3 py-2 text-sm text-white outline-none focus:border-cyan-400">
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
        <select x-model="model" class="mt-1 w-full rounded-md border border-neutral-800 bg-neutral-950 px-3 py-2 text-sm text-white outline-none focus:border-cyan-400">
            <template x-for="modelOption in modelOptions()" :key="modelOption.id">
                <option :value="modelOption.id" x-text="`${modelOption.label} (${modelOption.id})`"></option>
            </template>
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
    @error('selectedNodeId')
        <div class="mt-2 text-xs text-red-300">Select a section before applying an edit.</div>
    @enderror
    <button
        type="button"
        x-on:click="startEdit()"
        wire:loading.attr="disabled"
        wire:target="applyEditWithSelection,applyEdit"
        x-bind:disabled="editRunning"
        @disabled(! $selectedNodeId && count($selectedBlockIds) === 0)
        class="mt-3 inline-flex min-h-10 w-full items-center justify-center gap-2 rounded-md bg-cyan-500 px-3 py-2 text-sm font-semibold text-neutral-950 hover:bg-cyan-400 disabled:bg-neutral-800 disabled:text-neutral-400"
    >
        <span wire:loading wire:target="applyEditWithSelection,applyEdit" class="h-4 w-4 animate-spin rounded-full border-2 border-neutral-500 border-t-cyan-200"></span>
        <span x-show="editRunning" wire:loading.remove wire:target="applyEditWithSelection,applyEdit" class="h-4 w-4 animate-spin rounded-full border-2 border-neutral-500 border-t-cyan-200"></span>
        <span wire:loading wire:target="applyEditWithSelection,applyEdit">Applying edit</span>
        <span wire:loading.remove wire:target="applyEditWithSelection,applyEdit" x-text="editRunning ? 'Applying edit' : @js(count($selectedBlockIds) > 1 ? 'Apply multi edit' : 'Apply edit')"></span>
    </button>
</section>
