<div class="flex h-full flex-col" wire:ignore>
    <div class="flex h-12 items-center justify-between border-b border-neutral-800 px-4">
        <div>
            <div class="text-sm font-medium text-white">Canvas</div>
            <div class="text-xs text-neutral-500">{{ count($page->block_index ?? []) }} sections</div>
        </div>
        <div class="flex items-center gap-2">
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
                class="block w-full resize-y border-0 bg-neutral-950 px-3 py-3 font-mono text-xs leading-5 text-neutral-100 outline-none focus:ring-0"
            ></textarea>
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
