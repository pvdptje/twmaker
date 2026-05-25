<section
    class="border-b border-neutral-800 p-4"
    x-data="{
        provider: '',
        model: '',
        apiKey: '',
        insertRunning: false,
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
                    this.provider = localStorage.getItem(this.selectionKey('provider')) || localStorage.getItem(this.defaultKey('provider')) || '';
                    this.model = this.provider ? (localStorage.getItem(this.selectionKey(`model.${this.provider}`)) || localStorage.getItem(this.defaultKey('model')) || '') : '';
                }
            } catch (error) {}

            this.apiKey = this.provider ? (localStorage.getItem(this.storageKey(this.provider)) || '') : '';
        },
        updateSelection(event) {
            this.provider = event.detail?.provider || this.provider;
            this.model = event.detail?.model || this.model;
            this.apiKey = event.detail?.apiKey || '';
        },
        startInsert() {
            this.loadSelection();
            this.$wire.insertSectionWithSelection(this.provider || null, this.model || null, this.apiKey || null);
        },
        beginInsert(event) {
            if (event.detail?.pageId && event.detail.pageId !== @js($page->id)) return;
            if (event.detail?.stage !== 'section_inserter') return;
            this.insertRunning = true;
        },
        finishInsert(event) {
            if (event.detail?.pageId && event.detail.pageId !== @js($page->id)) return;
            this.insertRunning = false;
        },
    }"
    x-init="loadSelection()"
    x-on:builder-model-selection-changed.window="updateSelection($event)"
    x-on:generation-started.window="beginInsert($event)"
    x-on:generation-finished.window="finishInsert($event)"
>
    <div class="flex items-center justify-between gap-3">
        <div class="text-xs font-semibold uppercase tracking-normal text-neutral-500">Sections</div>
        <div class="flex items-center gap-2">
            @if (count($selectedBlockIds) > 0)
                <div class="text-xs text-cyan-300">{{ count($selectedBlockIds) }} selected</div>
            @endif
            <button
                type="button"
                wire:click="openInsert(null, 'after')"
                class="flex h-7 w-7 items-center justify-center rounded-md border border-neutral-700 bg-neutral-950 text-neutral-300 hover:border-cyan-500 hover:text-cyan-200"
                title="Insert section at end of page"
                aria-label="Insert section at end of page"
            >+</button>
        </div>
    </div>
    <div class="mt-3 flex flex-col gap-1">
        @php($sections = $blockIndex)
        @forelse ($sections as $section)
            @php($id = $section['id'] ?? '')
            @php($label = $section['label'] ?? $section['type'] ?? 'block')
            @php($isSelected = in_array($id, $selectedBlockIds, true))
            @if ($insertOpen && $insertAnchorBlockId === $id && $insertPosition === 'before')
                <form x-on:submit.prevent="startInsert()" class="mb-1 rounded-md border border-cyan-500/30 bg-neutral-950 p-2">
                    <textarea wire:model="insertInstruction" rows="3" class="w-full rounded-md border border-neutral-800 bg-neutral-900 px-2 py-1.5 text-sm text-white outline-none focus:border-cyan-400" placeholder="Describe the new section"></textarea>
                    @error('insertInstruction')
                        <div class="mt-1 text-xs text-red-300">{{ $message }}</div>
                    @enderror
                    <div class="mt-2 flex items-center gap-2">
                        <button type="submit" x-bind:disabled="insertRunning" wire:loading.attr="disabled" wire:target="insertSectionWithSelection,insertSection" class="inline-flex h-8 flex-1 items-center justify-center rounded-md bg-cyan-500 px-2 text-xs font-semibold text-neutral-950 hover:bg-cyan-400 disabled:bg-neutral-800 disabled:text-neutral-400">
                            <span wire:loading.remove wire:target="insertSectionWithSelection,insertSection" x-text="insertRunning ? 'Inserting' : 'Insert before'"></span>
                            <span wire:loading wire:target="insertSectionWithSelection,insertSection">Inserting</span>
                        </button>
                        <button type="button" wire:click="cancelInsert" class="h-8 rounded-md border border-neutral-700 px-2 text-xs text-neutral-300 hover:border-neutral-500">Cancel</button>
                    </div>
                </form>
            @endif
            <div
                x-data="{ menuOpen: false, confirmRemove: false, removing: false }"
                x-on:click.outside="menuOpen = false; confirmRemove = false"
                x-on:keydown.escape.window="menuOpen = false; confirmRemove = false"
                class="rounded-md {{ $isSelected ? 'bg-cyan-500/10 ring-1 ring-cyan-500/30' : 'hover:bg-neutral-800' }}"
            >
                <div class="flex items-center gap-2 px-2 py-1.5">
                    <input
                        type="checkbox"
                        @checked($isSelected)
                        wire:click.stop="$dispatch('block-selection-toggled', { blockId: @js($id) })"
                        class="h-4 w-4 rounded border-neutral-700 bg-neutral-950 text-cyan-400 focus:ring-cyan-400"
                        aria-label="Include {{ $label }} in multi edit"
                    >
                    <button
                        type="button"
                        onclick="window.dispatchEvent(new CustomEvent('preview-selection-changed', { detail: { nodeId: @js($id), scrollIntoView: true } }))"
                        wire:click="$dispatch('node-selected', { nodeId: @js($id), scrollIntoView: true })"
                        class="min-w-0 flex-1 text-left text-sm text-neutral-200"
                    >
                        <span class="block truncate">{{ $label }}</span>
                        <span class="block truncate text-xs text-neutral-500">{{ $id }}</span>
                    </button>
                    <button
                        type="button"
                        x-on:click.stop="menuOpen = !menuOpen; confirmRemove = false"
                        x-bind:aria-expanded="menuOpen ? 'true' : 'false'"
                        class="flex h-7 w-7 shrink-0 items-center justify-center rounded-md border border-transparent text-neutral-500 hover:border-neutral-700 hover:bg-neutral-900 hover:text-neutral-200"
                        title="Section actions"
                        aria-label="Section actions for {{ $label }}"
                    >
                        <svg viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4" aria-hidden="true">
                            <path d="M10 4a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3Zm0 4.5a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3Zm0 4.5a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3Z"/>
                        </svg>
                    </button>
                </div>
                <div
                    x-show="menuOpen"
                    x-cloak
                    x-transition.opacity.duration.100ms
                    class="mx-2 mb-2 rounded-md border border-neutral-800 bg-neutral-950 p-1 shadow-lg"
                >
                    <template x-if="!confirmRemove">
                        <div class="flex flex-col">
                            <button
                                type="button"
                                wire:click.stop="openInsert(@js($id), 'before')"
                                x-on:click="menuOpen = false"
                                class="flex items-center gap-2 rounded px-2 py-1.5 text-left text-xs text-neutral-200 hover:bg-neutral-800"
                            >
                                <span aria-hidden="true">&uarr;</span> Insert above
                            </button>
                            <button
                                type="button"
                                wire:click.stop="openInsert(@js($id), 'after')"
                                x-on:click="menuOpen = false"
                                class="flex items-center gap-2 rounded px-2 py-1.5 text-left text-xs text-neutral-200 hover:bg-neutral-800"
                            >
                                <span aria-hidden="true">&darr;</span> Insert below
                            </button>
                            <div class="my-1 border-t border-neutral-800"></div>
                            <button
                                type="button"
                                x-on:click="confirmRemove = true"
                                class="flex items-center gap-2 rounded px-2 py-1.5 text-left text-xs text-red-300 hover:bg-red-500/10"
                            >
                                <span aria-hidden="true">&times;</span> Remove section
                            </button>
                        </div>
                    </template>
                    <template x-if="confirmRemove">
                        <div class="p-1">
                            <div class="px-1 pb-2 text-xs text-neutral-300">Remove this section? This will save a version snapshot first.</div>
                            <div class="flex items-center gap-2">
                                <button
                                    type="button"
                                    x-on:click="removing = true; $wire.removeBlock(@js($id))"
                                    x-bind:disabled="removing"
                                    class="inline-flex h-8 flex-1 items-center justify-center rounded-md bg-red-500 px-2 text-xs font-semibold text-neutral-950 hover:bg-red-400 disabled:bg-neutral-800 disabled:text-neutral-400"
                                >
                                    <span x-show="!removing">Remove</span>
                                    <span x-show="removing">Removing</span>
                                </button>
                                <button
                                    type="button"
                                    x-on:click="confirmRemove = false; menuOpen = false"
                                    class="h-8 rounded-md border border-neutral-700 px-2 text-xs text-neutral-300 hover:border-neutral-500"
                                >Cancel</button>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
            @if ($insertOpen && $insertAnchorBlockId === $id && $insertPosition === 'after')
                <form x-on:submit.prevent="startInsert()" class="mt-1 rounded-md border border-cyan-500/30 bg-neutral-950 p-2">
                    <textarea wire:model="insertInstruction" rows="3" class="w-full rounded-md border border-neutral-800 bg-neutral-900 px-2 py-1.5 text-sm text-white outline-none focus:border-cyan-400" placeholder="Describe the new section"></textarea>
                    @error('insertInstruction')
                        <div class="mt-1 text-xs text-red-300">{{ $message }}</div>
                    @enderror
                    <div class="mt-2 flex items-center gap-2">
                        <button type="submit" x-bind:disabled="insertRunning" wire:loading.attr="disabled" wire:target="insertSectionWithSelection,insertSection" class="inline-flex h-8 flex-1 items-center justify-center rounded-md bg-cyan-500 px-2 text-xs font-semibold text-neutral-950 hover:bg-cyan-400 disabled:bg-neutral-800 disabled:text-neutral-400">
                            <span wire:loading.remove wire:target="insertSectionWithSelection,insertSection" x-text="insertRunning ? 'Inserting' : 'Insert after'"></span>
                            <span wire:loading wire:target="insertSectionWithSelection,insertSection">Inserting</span>
                        </button>
                        <button type="button" wire:click="cancelInsert" class="h-8 rounded-md border border-neutral-700 px-2 text-xs text-neutral-300 hover:border-neutral-500">Cancel</button>
                    </div>
                </form>
            @endif
        @empty
            <div class="rounded-md border border-dashed border-neutral-800 px-3 py-5 text-sm text-neutral-500">No sections yet.</div>
        @endforelse
        @if ($insertOpen && $insertAnchorBlockId === null)
            <form x-on:submit.prevent="startInsert()" class="mt-2 rounded-md border border-cyan-500/30 bg-neutral-950 p-2">
                <textarea wire:model="insertInstruction" rows="3" class="w-full rounded-md border border-neutral-800 bg-neutral-900 px-2 py-1.5 text-sm text-white outline-none focus:border-cyan-400" placeholder="Describe the new section"></textarea>
                @error('insertInstruction')
                    <div class="mt-1 text-xs text-red-300">{{ $message }}</div>
                @enderror
                <div class="mt-2 flex items-center gap-2">
                    <button type="submit" x-bind:disabled="insertRunning" wire:loading.attr="disabled" wire:target="insertSectionWithSelection,insertSection" class="inline-flex h-8 flex-1 items-center justify-center rounded-md bg-cyan-500 px-2 text-xs font-semibold text-neutral-950 hover:bg-cyan-400 disabled:bg-neutral-800 disabled:text-neutral-400">
                        <span wire:loading.remove wire:target="insertSectionWithSelection,insertSection" x-text="insertRunning ? 'Inserting' : 'Insert section'"></span>
                        <span wire:loading wire:target="insertSectionWithSelection,insertSection">Inserting</span>
                    </button>
                    <button type="button" wire:click="cancelInsert" class="h-8 rounded-md border border-neutral-700 px-2 text-xs text-neutral-300 hover:border-neutral-500">Cancel</button>
                </div>
            </form>
        @endif
    </div>
</section>
