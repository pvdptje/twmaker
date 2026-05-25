<section
    class="p-4"
    x-data="{
        provider: @js($provider),
        model: @js($model),
        apiKey: '',
        ...window.builderImageAttachments(),
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
                    this.updateAttachmentModalities(stored.modalities);
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
            this.updateAttachmentModalities(event.detail?.modalities);
        },
        startGenerate() {
            this.loadSelection();
            const payload = this.serializedAttachments();
            this.$wire.generateWithSelection(this.provider, this.model, this.apiKey, payload);
            this.clearAttachments();
        },
        startRefineEditability() {
            this.loadSelection();
            this.$wire.refineEditabilityWithSelection(this.provider, this.model, this.apiKey);
        },
    }"
    x-init="loadSelection()"
    x-on:builder-model-selection-changed.window="updateSelection($event)"
    x-on:dragover.prevent="dragOver = true"
    x-on:dragleave="if ($event.target === $el) dragOver = false"
    x-on:drop.prevent="dragOver = false; handleDrop($event)"
    x-bind:class="{ 'ring-2 ring-cyan-400/40': dragOver }"
>
    <div class="flex items-center justify-between gap-3">
        <div class="text-xs font-semibold uppercase tracking-normal text-neutral-500">Generation</div>
    </div>
    <textarea wire:model="prompt" x-on:paste="handlePaste($event)" rows="4" class="mt-3 w-full rounded-md border border-neutral-800 bg-neutral-950 px-3 py-2 text-sm text-white outline-none focus:border-cyan-400" placeholder="Describe the page (Cmd/Ctrl+V to paste a screenshot)"></textarea>
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
    <template x-if="attachments.length > 0">
        <div class="mt-2 flex flex-wrap gap-2">
            <template x-for="(att, idx) in attachments" :key="idx">
                <div class="relative">
                    <img :src="att.dataUrl" alt="" class="h-14 w-14 rounded border border-neutral-700 object-cover" />
                    <button
                        type="button"
                        x-on:click="removeAttachment(idx)"
                        class="absolute -right-1 -top-1 flex h-4 w-4 items-center justify-center rounded-full border border-neutral-700 bg-neutral-950 text-[10px] text-neutral-300 hover:bg-red-500 hover:text-white"
                        aria-label="Remove attachment"
                    >&times;</button>
                </div>
            </template>
        </div>
    </template>
    <template x-if="attachError">
        <div class="mt-2 text-xs text-red-300" x-text="attachError"></div>
    </template>
    <div class="mt-3 flex items-center gap-2">
        <button
            type="button"
            x-on:click="startGenerate()"
            wire:loading.attr="disabled"
            wire:target="generateWithSelection,generate"
            class="inline-flex h-10 flex-1 items-center justify-center rounded-md bg-cyan-500 px-3 text-sm font-semibold text-neutral-950 hover:bg-cyan-400 disabled:bg-neutral-800 disabled:text-neutral-400"
        >
            Generate
        </button>
        <label
            x-bind:class="visionAvailable ? 'cursor-pointer text-neutral-300 hover:text-white hover:border-neutral-600' : 'cursor-not-allowed text-neutral-600'"
            x-bind:title="visionAvailable ? 'Attach screenshot' : 'Pick a vision-capable model to attach images'"
            class="inline-flex h-10 w-10 items-center justify-center rounded-md border border-neutral-800 bg-neutral-900 transition"
            aria-label="Attach screenshot"
        >
            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" class="h-4 w-4" aria-hidden="true">
                <rect x="3" y="4" width="14" height="12" rx="1.5"/>
                <path d="M3 13l4-4 3 3 3-2 4 4" stroke-linecap="round" stroke-linejoin="round"/>
                <circle cx="13" cy="8" r="1.25" fill="currentColor" stroke="none"/>
            </svg>
            <input
                type="file"
                accept="image/png,image/jpeg,image/webp"
                class="hidden"
                x-bind:disabled="!visionAvailable"
                x-on:change="Array.from($event.target.files || []).forEach((file) => attachFile(file)); $event.target.value = '';"
            />
        </label>
    </div>
    <button
        type="button"
        x-on:click="startRefineEditability()"
        wire:loading.attr="disabled"
        wire:target="refineEditabilityWithSelection,refineEditability"
        @disabled(blank($page->html_source))
        class="mt-2 inline-flex min-h-9 w-full items-center justify-center rounded-md border border-neutral-700 px-3 py-2 text-xs font-medium text-neutral-200 hover:border-cyan-500 hover:text-cyan-100 disabled:border-neutral-800 disabled:text-neutral-600"
    >
        <span wire:loading.remove wire:target="refineEditabilityWithSelection,refineEditability">Make blocks more editable</span>
        <span wire:loading wire:target="refineEditabilityWithSelection,refineEditability">Refining editability</span>
    </button>
</section>
