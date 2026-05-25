<section class="border-b border-neutral-800 p-4">
    <div class="text-xs font-semibold uppercase tracking-normal text-neutral-500">Project</div>

    <details class="group relative mt-2">
        <summary class="flex cursor-pointer list-none items-center justify-between gap-2 rounded-md border border-neutral-800 bg-neutral-950 px-3 py-2 text-sm text-white hover:border-cyan-500 [&::-webkit-details-marker]:hidden">
            <span class="min-w-0">
                <span class="block truncate font-medium">{{ $project->name }}</span>
                <span class="mt-0.5 block truncate text-xs text-neutral-500">{{ $page->name }}</span>
            </span>
            <span class="shrink-0 text-xs text-neutral-500 transition group-open:rotate-180">v</span>
        </summary>

        <div class="absolute left-0 right-0 z-20 mt-2 overflow-hidden rounded-lg border border-neutral-800 bg-neutral-950 shadow-2xl shadow-black/40">
            <a
                href="{{ route('projects.show', $project) }}"
                wire:navigate
                class="block border-b border-neutral-800 px-3 py-2 text-xs font-medium text-cyan-300 hover:bg-neutral-900 hover:text-cyan-200"
            >
                Project dashboard
            </a>

            <div class="max-h-64 overflow-y-auto py-1">
                @forelse ($pages as $projectPage)
                    <a
                        href="{{ route('builder.workspace', [$project, $projectPage]) }}"
                        wire:navigate
                        class="block px-3 py-2 text-sm {{ $projectPage->id === $page->id ? 'bg-cyan-400/10 text-cyan-200' : 'text-neutral-300 hover:bg-neutral-900 hover:text-white' }}"
                    >
                        <span class="block truncate">{{ $projectPage->name }}</span>
                    </a>
                @empty
                    <div class="px-3 py-3 text-xs text-neutral-500">No pages yet.</div>
                @endforelse
            </div>
        </div>
    </details>
</section>
