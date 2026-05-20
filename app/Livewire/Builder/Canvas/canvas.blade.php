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
            class="h-full w-full rounded-lg border border-neutral-800 bg-white"
            srcdoc="{!! e($srcdoc) !!}">
        </iframe>
    </div>

    <script>
        window.addEventListener('message', (event) => {
            if (event.data?.type !== 'builder:node-selected') {
                return;
            }

            @this.selectNode(event.data.nodeId);
        });
    </script>
</div>
