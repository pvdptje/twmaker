<section class="border-b border-neutral-800 p-4">
    <div class="text-xs font-semibold uppercase tracking-normal text-neutral-500">Edit request</div>
    <textarea wire:model="instruction" rows="5" class="mt-3 w-full rounded-md border border-neutral-800 bg-neutral-950 px-3 py-2 text-sm text-white outline-none focus:border-cyan-400" placeholder="Describe the change for the selected section"></textarea>
    @error('instruction')
        <div class="mt-2 text-xs text-red-300">{{ $message }}</div>
    @enderror
    @error('selectedNodeId')
        <div class="mt-2 text-xs text-red-300">Select a section before applying an edit.</div>
    @enderror
    <button
        type="button"
        wire:click="applyEdit"
        wire:loading.attr="disabled"
        @disabled(! $selectedNodeId)
        class="mt-3 w-full rounded-md bg-cyan-500 px-3 py-2 text-sm font-semibold text-neutral-950 hover:bg-cyan-400 disabled:bg-neutral-800 disabled:text-neutral-400"
    >
        Apply edit
    </button>
</section>
