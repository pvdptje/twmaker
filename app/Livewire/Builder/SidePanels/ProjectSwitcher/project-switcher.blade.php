<section class="border-b border-neutral-800 p-4">
    <div class="text-xs font-semibold uppercase tracking-normal text-neutral-500">Project</div>
    <a href="{{ route('projects.show', $project) }}" wire:navigate class="mt-2 block rounded-md border border-neutral-800 bg-neutral-950 px-3 py-2 text-sm text-white hover:border-cyan-500">
        {{ $project->name }}
    </a>
</section>
