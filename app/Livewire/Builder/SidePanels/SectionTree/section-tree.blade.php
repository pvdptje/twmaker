<section class="border-b border-neutral-800 p-4">
    <div class="flex items-center justify-between gap-3">
        <div class="text-xs font-semibold uppercase tracking-normal text-neutral-500">Sections</div>
        @if (count($selectedBlockIds) > 0)
            <div class="text-xs text-cyan-300">{{ count($selectedBlockIds) }} selected</div>
        @endif
    </div>
    <div class="mt-3 flex flex-col gap-1">
        @php($sections = $blockIndex)
        @forelse ($sections as $section)
            @php($id = $section['id'] ?? '')
            @php($isSelected = in_array($id, $selectedBlockIds, true))
            <div class="flex items-center gap-2 rounded-md px-2 py-1.5 {{ $isSelected ? 'bg-cyan-500/10 ring-1 ring-cyan-500/30' : 'hover:bg-neutral-800' }}">
                <input
                    type="checkbox"
                    @checked($isSelected)
                    wire:click.stop="$dispatch('block-selection-toggled', { blockId: @js($id) })"
                    class="h-4 w-4 rounded border-neutral-700 bg-neutral-950 text-cyan-400 focus:ring-cyan-400"
                    aria-label="Include {{ $section['label'] ?? $section['type'] ?? 'block' }} in multi edit"
                >
                <button
                    type="button"
                    onclick="window.dispatchEvent(new CustomEvent('preview-selection-changed', { detail: { nodeId: @js($id), scrollIntoView: true } }))"
                    wire:click="$dispatch('node-selected', { nodeId: @js($id), scrollIntoView: true })"
                    class="min-w-0 flex-1 text-left text-sm text-neutral-200"
                >
                    <span class="block truncate">{{ $section['label'] ?? $section['type'] ?? 'block' }}</span>
                    <span class="block truncate text-xs text-neutral-500">{{ $id }}</span>
                </button>
            </div>
        @empty
            <div class="rounded-md border border-dashed border-neutral-800 px-3 py-5 text-sm text-neutral-500">No sections yet.</div>
        @endforelse
    </div>
</section>
