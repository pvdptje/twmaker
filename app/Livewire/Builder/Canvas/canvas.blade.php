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

            <div
                class="relative"
                x-data="{
                    device: 'desktop',
                    deviceOpen: false,
                    storageKey: 'twmaker.builder.previewDevice',
                    devices: [
                        { id: 'desktop', label: 'Desktop', width: 'Full' },
                        { id: 'tablet', label: 'Tablet', width: '768 px' },
                        { id: 'mobile', label: 'Mobile', width: '390 px' },
                    ],
                    init() {
                        const stored = localStorage.getItem(this.storageKey);
                        if (['desktop','tablet','mobile'].includes(stored)) this.device = stored;
                        this.apply();
                    },
                    select(id) {
                        this.device = id;
                        this.deviceOpen = false;
                        localStorage.setItem(this.storageKey, id);
                        this.apply();
                    },
                    currentLabel() {
                        const found = this.devices.find((d) => d.id === this.device);
                        return found ? found.label : 'Desktop';
                    },
                    apply() {
                        const frame = document.getElementById('builder-preview-frame');
                        if (!frame) return;
                        const widths = { desktop: '', tablet: '768px', mobile: '390px' };
                        const w = widths[this.device] || '';
                        frame.style.maxWidth = w;
                        frame.style.marginLeft = w ? 'auto' : '';
                        frame.style.marginRight = w ? 'auto' : '';
                    },
                }"
                x-on:click.outside="deviceOpen = false"
                x-on:keydown.escape.window="deviceOpen = false"
            >
                <button
                    type="button"
                    x-on:click="deviceOpen = !deviceOpen"
                    :title="'Preview width: ' + currentLabel()"
                    :aria-label="'Preview width: ' + currentLabel()"
                    :aria-haspopup="'menu'"
                    :aria-expanded="deviceOpen ? 'true' : 'false'"
                    class="inline-flex h-8 items-center gap-1.5 rounded-md border border-neutral-800 bg-neutral-900 px-2.5 text-xs font-medium text-neutral-300 transition hover:border-neutral-700 hover:text-white focus:outline-none focus:ring-2 focus:ring-cyan-400/40"
                >
                    <svg x-show="device === 'desktop'" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" class="h-3.5 w-3.5" aria-hidden="true">
                        <rect x="2.5" y="3.5" width="15" height="10" rx="1.25"/>
                        <path d="M7.5 17h5M10 13.5V17" stroke-linecap="round"/>
                    </svg>
                    <svg x-show="device === 'tablet'" x-cloak viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" class="h-3.5 w-3.5" aria-hidden="true">
                        <rect x="4" y="2.5" width="12" height="15" rx="1.5"/>
                        <path d="M9 15h2" stroke-linecap="round"/>
                    </svg>
                    <svg x-show="device === 'mobile'" x-cloak viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" class="h-3.5 w-3.5" aria-hidden="true">
                        <rect x="6" y="2" width="8" height="16" rx="1.5"/>
                        <path d="M9 15.5h2" stroke-linecap="round"/>
                    </svg>
                    <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" class="h-3 w-3 text-neutral-500" aria-hidden="true">
                        <path d="M6 8l4 4 4-4" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>
                <div
                    x-show="deviceOpen"
                    x-cloak
                    x-transition.opacity.duration.100ms
                    role="menu"
                    class="absolute right-0 top-9 z-40 w-40 rounded-md border border-neutral-800 bg-neutral-950 p-1 shadow-lg shadow-black/40"
                >
                    <template x-for="d in devices" :key="d.id">
                        <button
                            type="button"
                            role="menuitemradio"
                            x-on:click="select(d.id)"
                            x-bind:aria-checked="device === d.id ? 'true' : 'false'"
                            x-bind:class="device === d.id ? 'bg-neutral-800 text-white' : 'text-neutral-200 hover:bg-neutral-800'"
                            class="flex w-full items-center justify-between gap-2 rounded px-2 py-1.5 text-left text-xs"
                        >
                            <span x-text="d.label"></span>
                            <span class="text-[10px] text-neutral-500" x-text="d.width"></span>
                        </button>
                    </template>
                </div>
            </div>

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
