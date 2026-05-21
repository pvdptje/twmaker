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

    <div class="min-h-0 flex-1 bg-neutral-950 p-6">
        <iframe
            id="builder-preview-frame"
            title="Page preview"
            data-selected-node-id="{{ $selectedNodeId }}"
            class="h-full w-full rounded-lg border border-neutral-800 bg-white"
            srcdoc="{!! e($srcdoc) !!}">
        </iframe>
    </div>

    <script>
        function syncPreviewSelection(scrollIntoView = false) {
            const frame = document.getElementById('builder-preview-frame');

            if (!frame?.contentWindow) {
                return;
            }

            frame.contentWindow.postMessage({
                type: 'select-node',
                nodeId: frame.dataset.selectedNodeId || null,
                scrollIntoView: scrollIntoView === true,
            }, '*');
        }

        document.getElementById('builder-preview-frame')?.addEventListener('load', () => syncPreviewSelection(false));

        window.addEventListener('preview-selection-changed', (event) => {
            const frame = document.getElementById('builder-preview-frame');

            if (frame) {
                frame.dataset.selectedNodeId = event.detail?.nodeId || '';
            }

            syncPreviewSelection(event.detail?.scrollIntoView === true);
        });

        function registerLivewireSelectionSync() {
            if (!window.Livewire?.hook) {
                return;
            }

            window.Livewire.hook('morphed', syncPreviewSelection);
            window.Livewire.hook('morph.updated', syncPreviewSelection);
        }

        if (window.Livewire) {
            registerLivewireSelectionSync();
        } else {
            document.addEventListener('livewire:init', registerLivewireSelectionSync, { once: true });
        }

        window.addEventListener('message', (event) => {
            if (event.data?.type !== 'builder:node-selected') {
                return;
            }

            const frame = document.getElementById('builder-preview-frame');

            if (frame) {
                frame.dataset.selectedNodeId = event.data.nodeId || '';
            }

            syncPreviewSelection(false);

            if (window.Livewire?.dispatch) {
                window.Livewire.dispatch('node-selected', {
                    nodeId: event.data.nodeId,
                    scrollIntoView: false,
                });
            }
        });
    </script>
</div>
