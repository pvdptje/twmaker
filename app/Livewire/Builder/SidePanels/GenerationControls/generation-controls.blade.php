<section
    class="p-4"
    x-data="{
        provider: @js($provider),
        model: @js($model),
        apiKey: '',
        enhancementMenuOpen: false,
        customEnhancementOpen: false,
        customEnhancementPrompt: '',
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
        startEnhancement(enhancement) {
            this.loadSelection();
            this.$wire.runEnhancementWithSelection(enhancement, this.provider, this.model, this.apiKey, this.customEnhancementPrompt);
            this.enhancementMenuOpen = false;
            if (enhancement === 'custom') {
                this.customEnhancementPrompt = '';
                this.customEnhancementOpen = false;
            }
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
    <div class="relative mt-2" x-on:click.outside="enhancementMenuOpen = false">
        <button
            type="button"
            x-on:click="enhancementMenuOpen = !enhancementMenuOpen"
            wire:loading.attr="disabled"
            wire:target="runEnhancementWithSelection,runEnhancement"
            @disabled(blank($page->html_source))
            class="inline-flex min-h-9 w-full items-center justify-between rounded-md border border-neutral-700 px-3 py-2 text-xs font-medium text-neutral-200 hover:border-cyan-500 hover:text-cyan-100 disabled:border-neutral-800 disabled:text-neutral-600"
        >
            <span wire:loading.remove wire:target="runEnhancementWithSelection,runEnhancement">Enhancements</span>
            <span wire:loading wire:target="runEnhancementWithSelection,runEnhancement">Enhancing</span>
            <svg viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4" aria-hidden="true">
                <path fill-rule="evenodd" d="M5.23 12.79a.75.75 0 0 0 1.06-.02L10 8.83l3.71 3.94a.75.75 0 1 0 1.08-1.04l-4.25-4.5a.75.75 0 0 0-1.08 0l-4.25 4.5a.75.75 0 0 0 .02 1.06Z" clip-rule="evenodd" />
            </svg>
        </button>

        <div
            x-cloak
            x-show="enhancementMenuOpen"
            x-transition.origin.bottom
            class="absolute bottom-full left-0 z-20 mb-2 w-full overflow-hidden rounded-md border border-neutral-700 bg-neutral-950 shadow-xl shadow-black/40"
        >
            <button
                type="button"
                x-on:click="startEnhancement('editability')"
                class="flex w-full items-center justify-between gap-3 px-3 py-2 text-left text-xs text-neutral-200 hover:bg-neutral-900 hover:text-cyan-100"
            >
                <span>More editable blocks</span>
            </button>
            <button
                type="button"
                x-on:click="startEnhancement('color_scheme')"
                class="flex w-full items-center justify-between gap-3 border-t border-neutral-800 px-3 py-2 text-left text-xs text-neutral-200 hover:bg-neutral-900 hover:text-cyan-100"
            >
                <span>Refresh color scheme</span>
            </button>
            <button
                type="button"
                x-on:click="customEnhancementOpen = !customEnhancementOpen"
                class="flex w-full items-center justify-between gap-3 border-t border-neutral-800 px-3 py-2 text-left text-xs text-neutral-200 hover:bg-neutral-900 hover:text-cyan-100"
            >
                <span>Custom refinement</span>
            </button>
            <div x-show="customEnhancementOpen" class="border-t border-neutral-800 p-2">
                <textarea
                    x-model="customEnhancementPrompt"
                    rows="3"
                    class="w-full rounded-md border border-neutral-800 bg-neutral-950 px-2 py-1.5 text-xs text-white outline-none focus:border-cyan-400"
                    placeholder="Describe the refinement"
                ></textarea>
                @error('customEnhancementPrompt')
                    <div class="mt-1 text-xs text-red-300">{{ $message }}</div>
                @enderror
                <button
                    type="button"
                    x-on:click="startEnhancement('custom')"
                    class="mt-2 inline-flex h-8 w-full items-center justify-center rounded-md bg-cyan-500 px-3 text-xs font-semibold text-neutral-950 hover:bg-cyan-400 disabled:bg-neutral-800 disabled:text-neutral-400"
                    x-bind:disabled="customEnhancementPrompt.trim() === ''"
                >
                    Apply refinement
                </button>
            </div>
        </div>
    </div>
</section>
