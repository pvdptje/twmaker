<section
    class="border-b border-neutral-800 p-4"
    x-data="{
        provider: @js($provider),
        model: @js($model),
        ...window.builderImageAttachments(),
        sharedKey: 'twmaker.builder.modelSelection',
        defaultKey(field) { return `twmaker.llmDefaults.editing.${field}`; },
        selectionKey(field) { return `twmaker.builder.editing.${field}`; },
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

        },
        updateSelection(event) {
            this.provider = event.detail?.provider || this.provider;
            this.model = event.detail?.model || this.model;
            this.updateAttachmentModalities(event.detail?.modalities);
        },
        editRunning: false,
        startEdit() {
            this.loadSelection();
            const payload = this.serializedAttachments();
            this.$wire.applyEditWithSelection(this.provider, this.model, null, payload);
            this.clearAttachments();
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
    x-on:dragover.prevent="dragOver = true"
    x-on:dragleave="if ($event.target === $el) dragOver = false"
    x-on:drop.prevent="dragOver = false; handleDrop($event)"
    x-bind:class="{ 'ring-2 ring-cyan-400/40': dragOver }"
>
    <div class="flex items-center justify-between gap-3">
        <div class="text-xs font-semibold uppercase tracking-normal text-neutral-500">Edit request</div>
    </div>
    @if (count($selectedBlockIds) > 1)
        <div class="mt-2 rounded-md border border-cyan-500/40 bg-cyan-500/10 px-3 py-2 text-xs text-cyan-100">
            Multi edit: {{ count($selectedBlockIds) }} sections selected
        </div>
    @endif
    <textarea wire:model="instruction" x-on:paste="handlePaste($event)" rows="5" class="mt-3 w-full rounded-md border border-neutral-800 bg-neutral-950 px-3 py-2 text-sm text-white outline-none focus:border-cyan-400" placeholder="Describe the change for the selected section{{ count($selectedBlockIds) > 1 ? 's' : '' }} (Cmd/Ctrl+V to paste a screenshot)"></textarea>
    @error('instruction')
        <div class="mt-2 text-xs text-red-300">{{ $message }}</div>
    @enderror
    @error('provider')
        <div class="mt-2 text-xs text-red-300">{{ $message }}</div>
    @enderror
    @error('model')
        <div class="mt-2 text-xs text-red-300">{{ $message }}</div>
    @enderror
    @if ($modelCatalogStatus !== '')
        <div class="mt-2 text-xs text-neutral-500">{{ $modelCatalogStatus }}</div>
    @endif
    @error('selectedNodeId')
        <div class="mt-2 text-xs text-red-300">Select a section before applying an edit.</div>
    @enderror
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
            x-on:click="startEdit()"
            wire:loading.attr="disabled"
            wire:target="applyEditWithSelection,applyEdit"
            x-bind:disabled="editRunning"
            @disabled(! $selectedNodeId && count($selectedBlockIds) === 0)
            class="inline-flex min-h-10 flex-1 items-center justify-center gap-2 rounded-md bg-cyan-500 px-3 py-2 text-sm font-semibold text-neutral-950 hover:bg-cyan-400 disabled:bg-neutral-800 disabled:text-neutral-400"
        >
            <span wire:loading wire:target="applyEditWithSelection,applyEdit" class="h-4 w-4 animate-spin rounded-full border-2 border-neutral-500 border-t-cyan-200"></span>
            <span x-show="editRunning" wire:loading.remove wire:target="applyEditWithSelection,applyEdit" class="h-4 w-4 animate-spin rounded-full border-2 border-neutral-500 border-t-cyan-200"></span>
            <span wire:loading wire:target="applyEditWithSelection,applyEdit">Applying edit</span>
            <span wire:loading.remove wire:target="applyEditWithSelection,applyEdit" x-text="editRunning ? 'Applying edit' : @js(count($selectedBlockIds) > 1 ? 'Apply multi edit' : 'Apply edit')"></span>
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
</section>
