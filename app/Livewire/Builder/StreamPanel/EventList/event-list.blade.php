<div class="min-h-0 overflow-y-auto p-3" wire:poll.5s>
    @forelse ($events as $event)
        <div class="mb-2 rounded-md border border-neutral-800 bg-neutral-950 px-3 py-2">
            <div class="text-xs text-neutral-500">{{ $event->stage }} / {{ $event->kind }}</div>
            <div class="text-sm text-neutral-200">{{ $event->summary }}</div>
        </div>
    @empty
        <div class="flex h-full items-center justify-center text-sm text-neutral-500">No generation events yet.</div>
    @endforelse
</div>
