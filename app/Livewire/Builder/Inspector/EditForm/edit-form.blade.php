<section
    class="border-b border-neutral-800 p-4"
    x-data="{
        provider: @js($provider),
        model: @js($model),
        apiKey: '',
        sharedKey: 'twmaker.builder.modelSelection',
        storageKey(provider) { return `twmaker.apiKey.${provider}`; },
        defaultKey(field) { return `twmaker.llmDefaults.editing.${field}`; },
        selectionKey(field) { return `twmaker.builder.editing.${field}`; },
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
        editRunning: false,
        startEdit() {
            this.loadSelection();
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
    x-init="loadSelection()"
    x-on:builder-model-selection-changed.window="updateSelection($event)"
    x-on:generation-started.window="beginEdit($event)"
    x-on:generation-finished.window="finishEdit($event)"
>
    <div class="flex items-center justify-between gap-3">
        <div class="text-xs font-semibold uppercase tracking-normal text-neutral-500">Edit request</div>
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
