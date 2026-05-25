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
        <livewire:builder.inspector.version-list.version-list :page="$page" />
    </div>
    <div class="border-t border-neutral-800 p-4">
        <div class="text-xs font-semibold uppercase tracking-normal text-neutral-500">Tokens</div>
        @forelse ($usageTotals as $usage)
            <div class="mt-2 rounded-md border border-neutral-800 bg-neutral-950 px-2 py-1.5">
                <div class="truncate text-xs font-medium text-neutral-200" title="{{ $usage['model'] }}">{{ $usage['model'] }}</div>
                @if ($usage['provider'] !== '')
                    <div class="mt-0.5 text-[11px] text-neutral-600">{{ $usage['provider'] }}</div>
                @endif
                <div class="mt-1 text-xs text-neutral-500">
                    {{ number_format($usage['total']) }} total
                    @if ($usage['cost'] !== null)
                        <span class="text-neutral-700">/</span>
                        {{ $usage['cost']['currency'] }} {{ number_format($usage['cost']['amount'], 4) }}
                    @endif
                </div>
            </div>
        @empty
            <div class="mt-2 text-xs text-neutral-500">No token usage yet.</div>
        @endforelse
    </div>
</div>
