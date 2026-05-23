<main
    data-builder-workspace-page-id="{{ $page->id }}"
    class="grid h-screen min-h-[44rem] grid-cols-[18rem_minmax(0,1fr)_20rem] grid-rows-[1fr_12rem] bg-neutral-950 text-neutral-100"
>
    <aside class="row-span-2 border-r border-neutral-800 bg-neutral-900">
        <livewire:builder.left-sidebar.left-sidebar :project="$project" :page="$page" :block-index="$block_index" :selected-block-ids="$selected_block_ids" :key="'left-'.$page->id" />
    </aside>

    <section class="min-h-0 bg-neutral-950">
        <livewire:builder.canvas.canvas :page="$page" :selected-node-id="$selected_node_id" :key="'canvas-'.$page->id.'-'.$preview_mount_key" />
    </section>

    <aside class="row-span-2 border-l border-neutral-800 bg-neutral-900">
        <livewire:builder.right-inspector.right-inspector :page="$page" :selected-node-id="$selected_node_id" :selected-block-ids="$selected_block_ids" :key="'inspector-'.$page->id" />
    </aside>

    <section class="border-t border-neutral-800 bg-neutral-900">
        <livewire:builder.stream-panel.stream-panel :page="$page" :generation-status="$generation_status" />
    </section>
</main>
