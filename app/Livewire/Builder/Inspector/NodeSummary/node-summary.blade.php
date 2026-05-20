<section class="border-b border-neutral-800 p-4">
    <div class="text-xs font-semibold uppercase tracking-normal text-neutral-500">Selected node</div>
    @if ($selectedNodeId)
        <div class="mt-3 rounded-md border border-neutral-800 bg-neutral-950 p-3 text-sm text-neutral-200">{{ $selectedNodeId }}</div>
    @else
        <div class="mt-3 rounded-md border border-dashed border-neutral-800 p-3 text-sm text-neutral-500">Select a node from the canvas or section tree.</div>
    @endif
</section>
