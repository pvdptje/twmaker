<div class="flex h-full flex-col">
    <div
        class="border-b border-neutral-800 p-4"
        x-data="{
            provider: '',
            model: '',
            apiKey: '',
            sharedKey: 'twmaker.builder.modelSelection',
            storageKey(provider) { return `twmaker.apiKey.${provider}`; },
            loadSelection() {
                try {
                    const stored = JSON.parse(localStorage.getItem(this.sharedKey) || 'null');
                    this.provider = stored?.provider || '';
                    this.model = stored?.model || '';
                } catch (error) {}
                this.apiKey = this.provider ? (localStorage.getItem(this.storageKey(this.provider)) || '') : '';
            },
            createRelatedPage() {
                this.loadSelection();
                this.$wire.createRelatedPageWithSelection(this.provider || null, this.model || null, this.apiKey || null);
            },
        }"
        x-init="loadSelection()"
        x-on:builder-model-selection-changed.window="provider = $event.detail?.provider || provider; model = $event.detail?.model || model; apiKey = $event.detail?.apiKey || ''"
    >
        <div class="flex items-center justify-between gap-3">
            <div class="min-w-0 text-sm font-semibold text-white">
                <span class="block truncate">{{ $project->name }}</span>
            </div>
            <button
                type="button"
                wire:click="openCreateRelatedPage"
                @disabled(blank($page->html_source))
                class="flex h-7 w-7 shrink-0 items-center justify-center rounded-md border border-neutral-700 bg-neutral-950 text-neutral-300 hover:border-cyan-500 hover:text-cyan-200 disabled:cursor-not-allowed disabled:border-neutral-800 disabled:text-neutral-600"
                title="{{ blank($page->html_source) ? 'Generate this page first' : 'Create page from current page' }}"
                aria-label="Create page from current page"
            >+</button>
        </div>
        <div class="mt-1 text-xs text-neutral-400">{{ $page->name }}</div>

        @if ($createRelatedOpen)
            <form
                x-on:submit.prevent="createRelatedPage()"
                class="mt-3 rounded-md border border-cyan-500/30 bg-neutral-950 p-2"
            >
                <input
                    wire:model="relatedPageName"
                    type="text"
                    class="w-full rounded-md border border-neutral-800 bg-neutral-900 px-2 py-1.5 text-sm text-white outline-none focus:border-cyan-400"
                    placeholder="New page name"
                    autocomplete="off"
                >
                @error('relatedPageName')
                    <div class="mt-1 text-xs text-red-300">{{ $message }}</div>
                @enderror

                <textarea
                    wire:model="relatedPageBrief"
                    rows="3"
                    class="mt-2 w-full rounded-md border border-neutral-800 bg-neutral-900 px-2 py-1.5 text-sm text-white outline-none focus:border-cyan-400"
                    placeholder="Brief, e.g. pricing page or contact page"
                ></textarea>
                @error('relatedPageBrief')
                    <div class="mt-1 text-xs text-red-300">{{ $message }}</div>
                @enderror

                <div class="mt-2 flex items-center gap-2">
                    <button
                        type="submit"
                        wire:loading.attr="disabled"
                        wire:target="createRelatedPageWithSelection,createRelatedPage"
                        class="inline-flex h-8 flex-1 items-center justify-center rounded-md bg-cyan-500 px-2 text-xs font-semibold text-neutral-950 hover:bg-cyan-400 disabled:bg-neutral-800 disabled:text-neutral-400"
                    >
                        <span wire:loading.remove wire:target="createRelatedPageWithSelection,createRelatedPage">Generate page</span>
                        <span wire:loading wire:target="createRelatedPageWithSelection,createRelatedPage">Starting</span>
                    </button>
                    <button
                        type="button"
                        wire:click="cancelCreateRelatedPage"
                        class="h-8 rounded-md border border-neutral-700 px-2 text-xs text-neutral-300 hover:border-neutral-500"
                    >Cancel</button>
                </div>
            </form>
        @endif
    </div>

    <div class="min-h-0 flex-1 overflow-y-auto">
        <livewire:builder.side-panels.project-switcher.project-switcher :project="$project" />
        <livewire:builder.side-panels.section-tree.section-tree
            :page="$page"
            :block-index="$blockIndex"
            :selected-node-id="$selectedNodeId"
            :selected-block-ids="$selectedBlockIds"
            :key="'section-tree-'.md5(json_encode($blockIndex)).'-'.md5((string) $selectedNodeId).'-'.md5(json_encode($selectedBlockIds))"
        />
    </div>

    <div class="border-t border-neutral-800">
        <livewire:builder.side-panels.generation-controls.generation-controls :page="$page" />
    </div>
</div>
