<div class="flex h-full flex-col" wire:ignore>
    <div class="flex h-12 items-center justify-between border-b border-neutral-800 px-4">
        <div>
            <div class="text-sm font-medium text-white">Canvas</div>
            <div class="text-xs text-neutral-500">{{ $sectionCount }} sections</div>
        </div>
        <div class="relative z-30 flex min-w-0 items-center gap-2">
            <livewire:builder.model-selector.model-selector :key="'model-selector-'.$page->id" />
            <span class="rounded bg-neutral-900 px-2 py-1 text-xs text-neutral-400">Live preview</span>
            @if (filled($page->html_source))
                <a
                    href="{{ route('builder.pages.download-html', [$page->project_id, $page]) }}"
                    class="rounded-md border border-cyan-500/40 bg-cyan-500/10 px-3 py-1.5 text-xs font-medium text-cyan-200 transition hover:border-cyan-400 hover:bg-cyan-500/20 hover:text-white focus:outline-none focus:ring-2 focus:ring-cyan-400/40"
                >
                    Download HTML
                </a>
            @else
                <span class="cursor-not-allowed rounded-md border border-neutral-800 bg-neutral-900 px-3 py-1.5 text-xs font-medium text-neutral-600">
                    Download HTML
                </span>
            @endif

            <button
                type="button"
                x-data="{
                    expanded: false,
                    storageKey: 'twmaker.builder.previewExpanded',
                    init() {
                        this.expanded = localStorage.getItem(this.storageKey) === '1';
                        this.apply();
                    },
                    toggle() {
                        this.expanded = !this.expanded;
                        localStorage.setItem(this.storageKey, this.expanded ? '1' : '0');
                        this.apply();
                    },
                    apply() {
                        const main = document.querySelector('main[data-builder-workspace-page-id]');
                        if (!main) return;
                        main.style.gridTemplateColumns = this.expanded ? 'minmax(0,1fr)' : '';
                        main.querySelectorAll(':scope > aside').forEach((aside) => {
                            aside.style.display = this.expanded ? 'none' : '';
                        });
                    },
                }"
                @click="toggle()"
                :title="expanded ? 'Restore sidebars' : 'Expand preview'"
                :aria-label="expanded ? 'Restore sidebars' : 'Expand preview'"
                :aria-pressed="expanded"
                class="inline-flex h-8 items-center gap-1.5 rounded-md border border-neutral-800 bg-neutral-900 px-2.5 text-xs font-medium text-neutral-300 transition hover:border-neutral-700 hover:text-white focus:outline-none focus:ring-2 focus:ring-cyan-400/40"
            >
                <svg x-show="!expanded" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" class="h-3.5 w-3.5" aria-hidden="true">
                    <path d="M3 8V3h5M17 8V3h-5M3 12v5h5M17 12v5h-5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <svg x-show="expanded" x-cloak viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" class="h-3.5 w-3.5" aria-hidden="true">
                    <path d="M8 3v5H3M12 3v5h5M8 17v-5H3M12 17v-5h5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <span x-text="expanded ? 'Collapse' : 'Expand'"></span>
            </button>
        </div>
    </div>

    <div id="builder-canvas-stage" class="relative min-h-0 flex-1 bg-neutral-950 p-6">
        <iframe
            id="builder-preview-frame"
            title="Page preview"
            data-selected-node-id="{{ $selectedNodeId }}"
            class="h-full w-full rounded-lg border border-neutral-800 bg-white"
            srcdoc="{!! e($srcdoc) !!}">
        </iframe>

        <div
            id="builder-quick-editor"
            class="hidden absolute z-20 w-[min(34rem,calc(100%-3rem))] rounded-lg border border-neutral-700 bg-neutral-950 shadow-2xl shadow-black/40"
        >
            <div class="flex items-center justify-between border-b border-neutral-800 px-3 py-2">
                <div>
                    <div class="text-xs font-medium uppercase tracking-normal text-cyan-300" id="builder-quick-editor-tag">HTML</div>
                    <div class="text-[11px] text-neutral-500" id="builder-quick-editor-target"></div>
                </div>
                <button
                    type="button"
                    id="builder-quick-editor-close"
                    class="rounded-md px-2 py-1 text-xs text-neutral-400 hover:bg-neutral-900 hover:text-white"
                    aria-label="Close quick editor"
                >
                    x
                </button>
            </div>
            <textarea
                id="builder-quick-editor-html"
                rows="9"
                spellcheck="false"
                class="hidden"
            ></textarea>
            <div id="builder-quick-editor-code" class="min-h-56 border-0 bg-neutral-950"></div>
            <div id="builder-quick-editor-error" class="hidden border-t border-red-500/20 px-3 py-2 text-xs text-red-300"></div>
            <div class="flex items-center justify-end gap-2 border-t border-neutral-800 px-3 py-2">
                <button
                    type="button"
                    id="builder-quick-editor-cancel"
                    class="rounded-md border border-neutral-800 px-3 py-1.5 text-xs font-medium text-neutral-300 hover:border-neutral-700 hover:bg-neutral-900 hover:text-white"
                >
                    Cancel
                </button>
                <button
                    type="button"
                    id="builder-quick-editor-save"
                    class="rounded-md border border-cyan-500/50 bg-cyan-500/10 px-3 py-1.5 text-xs font-medium text-cyan-100 hover:border-cyan-400 hover:bg-cyan-500/20 hover:text-white"
                >
                    Save
                </button>
            </div>
        </div>
    </div>
</div>
