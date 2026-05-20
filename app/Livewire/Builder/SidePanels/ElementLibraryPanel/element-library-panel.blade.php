<section class="border-b border-neutral-800 p-4">
    <div class="text-xs font-semibold uppercase tracking-normal text-neutral-500">Element library</div>
    <div class="mt-3 flex flex-col gap-2">
        @forelse ($library as $element)
            <div class="rounded-md border border-neutral-800 bg-neutral-950 px-3 py-2">
                <div class="text-sm text-white">{{ $element['name'] }}</div>
                <div class="text-xs text-neutral-500">{{ $element['type'] }}</div>
            </div>
        @empty
            <div class="rounded-md border border-dashed border-neutral-800 px-3 py-5 text-sm text-neutral-500">No saved elements.</div>
        @endforelse
    </div>
</section>
