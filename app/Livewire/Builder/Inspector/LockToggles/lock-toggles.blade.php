<section class="p-4">
    <div class="text-xs font-semibold uppercase tracking-normal text-neutral-500">Locks</div>
    <div class="mt-3 flex flex-col gap-2 text-sm text-neutral-300">
        <label class="flex items-center justify-between gap-3"><span>Content</span><input type="checkbox" wire:model="contentLocked" disabled></label>
        <label class="flex items-center justify-between gap-3"><span>Style</span><input type="checkbox" wire:model="styleLocked" disabled></label>
        <label class="flex items-center justify-between gap-3"><span>Layout</span><input type="checkbox" wire:model="layoutLocked" disabled></label>
    </div>
</section>
