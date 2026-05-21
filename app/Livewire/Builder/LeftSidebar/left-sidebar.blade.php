<div class="flex h-full flex-col">
    <div class="border-b border-neutral-800 p-4">
        <div class="text-sm font-semibold text-white">{{ $project->name }}</div>
        <div class="mt-1 text-xs text-neutral-400">{{ $page->name }}</div>
    </div>

    <div class="min-h-0 flex-1 overflow-y-auto">
        <livewire:builder.side-panels.project-switcher.project-switcher :project="$project" />
        <livewire:builder.side-panels.section-tree.section-tree :block-index="$blockIndex" :selected-block-ids="$selectedBlockIds" />
    </div>

    <div class="border-t border-neutral-800">
        <livewire:builder.side-panels.generation-controls.generation-controls :page="$page" />
    </div>
</div>
