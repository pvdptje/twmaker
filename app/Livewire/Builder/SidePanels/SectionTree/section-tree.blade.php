<section class="border-b border-neutral-800 p-4">
    <div class="text-xs font-semibold uppercase tracking-normal text-neutral-500">Sections</div>
    <div class="mt-3 flex flex-col gap-1">
        @php($sections = $blockIndex)
        @forelse ($sections as $section)
            @php($id = $section['id'] ?? '')
            <button
                type="button"
                onclick="window.dispatchEvent(new CustomEvent('preview-selection-changed', { detail: { nodeId: @js($id), scrollIntoView: true } }))"
                wire:click="$dispatch('node-selected', { nodeId: @js($id), scrollIntoView: true })"
                class="rounded-md px-3 py-2 text-left text-sm text-neutral-200 hover:bg-neutral-800"
            >
                {{ $section['label'] ?? $section['type'] ?? 'block' }} <span class="text-xs text-neutral-500">{{ $id }}</span>
            </button>
        @empty
            <div class="rounded-md border border-dashed border-neutral-800 px-3 py-5 text-sm text-neutral-500">No sections yet.</div>
        @endforelse
    </div>
</section>
