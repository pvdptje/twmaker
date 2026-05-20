<section class="p-4">
    <div class="text-xs font-semibold uppercase tracking-normal text-neutral-500">Generation</div>
    <textarea wire:model="prompt" rows="4" class="mt-3 w-full rounded-md border border-neutral-800 bg-neutral-950 px-3 py-2 text-sm text-white outline-none focus:border-cyan-400"></textarea>
    @error('prompt')
        <div class="mt-2 text-xs text-red-300">{{ $message }}</div>
    @enderror
    <button type="button" wire:click="generate" class="mt-3 w-full rounded-md bg-cyan-500 px-3 py-2 text-sm font-semibold text-neutral-950 hover:bg-cyan-400">Generate</button>
</section>
