@php
    $statusClass = match ($statusLabel) {
        'running' => 'border-cyan-400/40 bg-cyan-400/10 text-cyan-100',
        'valid' => 'border-emerald-400/40 bg-emerald-400/10 text-emerald-100',
        'error' => 'border-red-400/40 bg-red-400/10 text-red-100',
        default => 'border-neutral-700 bg-neutral-800 text-neutral-300',
    };
@endphp

<div class="grid h-full grid-cols-[12rem_1fr]" wire:poll.5s>
    <div class="border-r border-neutral-800 p-4">
        <div class="text-sm font-semibold text-white">Stream</div>
        <div class="mt-2 rounded-md border px-2 py-1 text-xs font-medium {{ $statusClass }}">{{ $statusLabel }}</div>

        @if ($statusLabel === 'running')
            <div class="mt-3 overflow-hidden rounded-full bg-neutral-800">
                <div class="h-1.5 w-1/2 animate-pulse rounded-full bg-gradient-to-r from-cyan-300 via-violet-300 to-emerald-300"></div>
            </div>
            <div class="mt-2 flex items-center gap-1 text-xs text-cyan-200">
                <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-cyan-300"></span>
                <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-violet-300 [animation-delay:120ms]"></span>
                <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-emerald-300 [animation-delay:240ms]"></span>
            </div>
        @endif

        <div class="mt-4 border-t border-neutral-800 pt-3">
            <div class="text-xs font-semibold uppercase tracking-normal text-neutral-500">Tokens</div>
            @forelse ($usageTotals as $model => $usage)
                <div class="mt-2 rounded-md border border-neutral-800 bg-neutral-950 px-2 py-1.5">
                    <div class="truncate text-xs font-medium text-neutral-200" title="{{ $model }}">{{ $model }}</div>
                    <div class="mt-1 text-xs text-neutral-500">
                        {{ number_format($usage['total']) }} total
                    </div>
                </div>
            @empty
                <div class="mt-2 text-xs text-neutral-500">No token usage yet.</div>
            @endforelse
        </div>
    </div>
    <livewire:builder.stream-panel.event-list.event-list :page="$page" />
</div>
