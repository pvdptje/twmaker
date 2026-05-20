<section class="border-b border-neutral-800 p-4">
    <div class="text-xs font-semibold uppercase tracking-normal text-neutral-500">Sections</div>
    <div class="mt-3 flex flex-col gap-1">
        @forelse (($document['document_tree'] ?? []) as $section)
            <button type="button" wire:click="$parent.$parent.selectNode('{{ $section['id'] }}')" class="rounded-md px-3 py-2 text-left text-sm text-neutral-200 hover:bg-neutral-800">
                {{ $section['type'] }} <span class="text-xs text-neutral-500">{{ $section['id'] }}</span>
            </button>
        @empty
            <div class="rounded-md border border-dashed border-neutral-800 px-3 py-5 text-sm text-neutral-500">No sections yet.</div>
        @endforelse
    </div>
</section>
