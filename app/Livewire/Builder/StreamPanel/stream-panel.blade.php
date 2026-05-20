<div class="grid h-full grid-cols-[12rem_1fr]" wire:poll.5s>
    <div class="border-r border-neutral-800 p-4">
        <div class="text-sm font-semibold text-white">Stream</div>
        <div class="mt-2 rounded bg-neutral-800 px-2 py-1 text-xs text-neutral-300">{{ $statusLabel }}</div>
    </div>
    <livewire:builder.stream-panel.event-list.event-list :page="$page" />
</div>
