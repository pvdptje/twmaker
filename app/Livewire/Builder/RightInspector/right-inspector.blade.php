<div class="flex h-full flex-col">
    <div class="border-b border-neutral-800 p-4">
        <div class="text-sm font-semibold text-white">Inspector</div>
        <div class="mt-1 text-xs text-neutral-500">{{ $selectedNodeId ?: 'No selection' }}</div>
        @if (count($selectedBlockIds) > 0)
            <div class="mt-1 text-xs text-cyan-300">{{ count($selectedBlockIds) }} multi-edit selection{{ count($selectedBlockIds) === 1 ? '' : 's' }}</div>
        @endif
    </div>
    <div class="min-h-0 flex-1 overflow-y-auto">
        <livewire:builder.inspector.node-summary.node-summary :selected-node-id="$selectedNodeId" />
        <livewire:builder.inspector.edit-form.edit-form :page="$page" :selected-node-id="$selectedNodeId" :selected-block-ids="$selectedBlockIds" />
    </div>
</div>
