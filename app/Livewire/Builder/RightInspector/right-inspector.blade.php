<div class="flex h-full flex-col">
    <div class="border-b border-neutral-800 p-4">
        <div class="text-sm font-semibold text-white">Inspector</div>
        <div class="mt-1 text-xs text-neutral-500">{{ $selectedNodeId ?: 'No selection' }}</div>
    </div>
    <div class="min-h-0 flex-1 overflow-y-auto">
        <livewire:builder.inspector.node-summary.node-summary :document="$document" :selected-node-id="$selectedNodeId" />
        <livewire:builder.inspector.edit-form.edit-form :selected-node-id="$selectedNodeId" />
        <livewire:builder.inspector.lock-toggles.lock-toggles :selected-node-id="$selectedNodeId" />
    </div>
</div>
