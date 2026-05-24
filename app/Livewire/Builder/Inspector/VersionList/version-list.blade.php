<section class="border-b border-neutral-800 p-4">
    <div class="flex items-center justify-between gap-2">
        <div class="text-xs font-semibold uppercase tracking-normal text-neutral-500">Version history</div>
        <div class="text-[10px] text-neutral-600">{{ count($versions) }} saved</div>
    </div>

    @if (count($versions) === 0)
        <div class="mt-3 rounded-md border border-dashed border-neutral-800 px-3 py-4 text-center text-xs text-neutral-500">
            No snapshots yet. Generate or edit a page to create one.
        </div>
    @else
        <ol class="mt-3 max-h-72 space-y-1.5 overflow-y-auto pr-1" data-version-list>
            @foreach ($versions as $version)
                <li>
                    <button
                        type="button"
                        wire:click="restore('{{ $version['id'] }}')"
                        wire:loading.attr="disabled"
                        wire:target="restore('{{ $version['id'] }}')"
                        class="group flex w-full items-start gap-2 rounded-md border px-2.5 py-2 text-left transition {{ $version['id'] === $activeVersionId
                            ? 'border-cyan-500/50 bg-cyan-500/10 text-cyan-50'
                            : 'border-neutral-800 bg-neutral-950 text-neutral-300 hover:border-neutral-700 hover:bg-neutral-900' }}"
                    >
                        <span class="mt-1 h-1.5 w-1.5 shrink-0 rounded-full {{ $version['kind'] === 'generation' ? 'bg-violet-400' : 'bg-cyan-400' }}"></span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-xs font-medium">{{ $version['summary'] }}</span>
                            <span class="mt-0.5 block text-[10px] uppercase tracking-normal text-neutral-500">
                                {{ $version['kind'] === 'generation' ? 'Generation' : 'Edit' }} · {{ $version['created_at'] }}
                            </span>
                        </span>
                        @if ($version['id'] === $activeVersionId)
                            <span class="mt-0.5 shrink-0 rounded-sm bg-cyan-500/20 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-normal text-cyan-100">Active</span>
                        @endif
                    </button>
                </li>
            @endforeach
        </ol>
    @endif
</section>
