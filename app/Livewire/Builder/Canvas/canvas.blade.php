<div class="flex h-full flex-col" wire:ignore>
    <div class="flex h-12 items-center justify-between border-b border-neutral-800 px-4">
        <div>
            <div class="text-sm font-medium text-white">Canvas</div>
            <div class="text-xs text-neutral-500">{{ count($document['document_tree'] ?? []) }} sections</div>
        </div>
        <span class="rounded bg-neutral-900 px-2 py-1 text-xs text-neutral-400">Live preview</span>
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
        function syncPreviewSelection() {
            const frame = document.getElementById('builder-preview-frame');

            if (!frame?.contentWindow) {
                return;
            }

            frame.contentWindow.postMessage({
                type: 'select-node',
                nodeId: frame.dataset.selectedNodeId || null,
            }, '*');
        }

        document.getElementById('builder-preview-frame')?.addEventListener('load', syncPreviewSelection);

        window.addEventListener('preview-selection-changed', (event) => {
            const frame = document.getElementById('builder-preview-frame');

            if (frame) {
                frame.dataset.selectedNodeId = event.detail.nodeId || '';
            }

            syncPreviewSelection();
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

            syncPreviewSelection();

            if (window.Livewire?.dispatch) {
                window.Livewire.dispatch('node-selected', {
                    nodeId: event.data.nodeId,
                });
            }
        });
    </script>
</div>
