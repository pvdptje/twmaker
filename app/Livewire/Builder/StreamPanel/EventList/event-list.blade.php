<div
    class="min-h-0 overflow-y-auto p-3"
    wire:poll.5s
    x-data="{
        now: Date.now(),
        maxRows: 80,
        elapsed(iso) {
            const seconds = Math.max(0, Math.floor((this.now - Date.parse(iso)) / 1000));
            if (seconds < 60) return `${seconds}s`;
            const minutes = Math.floor(seconds / 60);
            const rest = seconds % 60;
            return `${minutes}m ${rest.toString().padStart(2, '0')}s`;
        },
        prune() {
            const rows = Array.from(this.$el.querySelectorAll('[data-generation-event-row]'));
            rows.slice(this.maxRows).forEach((row) => row.remove());
        },
    }"
    x-init="
        setInterval(() => now = Date.now(), 1000);
        prune();
        new MutationObserver(() => prune()).observe($el, { childList: true, subtree: false });
    "
>
    @forelse ($events as $event)
        @php
            $isRunning = $loop->first && (($event->level === 'info' && str_ends_with((string) $event->kind, 'started')) || $event->kind === 'edit_requested');
            $rowClass = match ($event->level) {
                'success' => 'border-emerald-400/25 bg-gradient-to-r from-emerald-400/10 via-neutral-950 to-neutral-950',
                'error' => 'border-red-400/30 bg-gradient-to-r from-red-500/10 via-neutral-950 to-neutral-950',
                default => $isRunning
                    ? 'border-cyan-400/25 bg-gradient-to-r from-cyan-400/10 via-neutral-950 to-neutral-950'
                    : 'border-neutral-800 bg-neutral-950',
            };
            $iconClass = match ($event->level) {
                'success' => 'border-emerald-400/40 bg-emerald-400/15 text-emerald-200',
                'error' => 'border-red-400/40 bg-red-400/15 text-red-200',
                default => 'border-cyan-400/30 bg-cyan-400/10 text-cyan-200',
            };
        @endphp

        <div data-generation-event-row class="mb-2 rounded-md border px-3 py-2 shadow-sm {{ $rowClass }}">
            <div class="flex items-start gap-3">
                <div class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full border {{ $iconClass }}">
                    @if ($event->level === 'success')
                        <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M16.704 5.29a1 1 0 0 1 .006 1.414l-7.25 7.32a1 1 0 0 1-1.421 0L3.29 9.23a1 1 0 1 1 1.42-1.408l4.04 4.08 6.54-6.606a1 1 0 0 1 1.414-.006Z" clip-rule="evenodd" />
                        </svg>
                    @elseif ($event->level === 'error')
                        <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" clip-rule="evenodd" />
                        </svg>
                    @else
                        <span class="h-2 w-2 rounded-full bg-current {{ $isRunning ? 'animate-pulse' : '' }}"></span>
                    @endif
                </div>
                <div class="min-w-0 flex-1">
                    <div class="text-xs text-neutral-500">{{ $event->stage }} / {{ $event->kind }}</div>
                    <div class="text-sm text-neutral-200">{{ $event->summary }}</div>
                    @if ($isRunning)
                        <div class="mt-1 text-xs text-cyan-200">
                            Running for <span x-text="elapsed(@js($event->occurred_at?->toIso8601String()))"></span>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @empty
        <div class="flex h-full items-center justify-center text-sm text-neutral-500">No generation events yet.</div>
    @endforelse
</div>
