<div class="flex h-full flex-col">
    <div class="flex h-12 items-center justify-between border-b border-neutral-800 px-4">
        <div>
            <div class="text-sm font-medium text-white">Canvas</div>
            <div class="text-xs text-neutral-500">{{ count($document['document_tree'] ?? []) }} sections</div>
        </div>
        <span class="rounded bg-neutral-900 px-2 py-1 text-xs text-neutral-400">Preview placeholder</span>
    </div>

    <div class="min-h-0 flex-1 bg-neutral-950 p-6">
        <iframe
            title="Page preview"
            class="h-full w-full rounded-lg border border-neutral-800 bg-white"
            srcdoc='<!doctype html><html><head><link rel="stylesheet" href="/preview.css"></head><body style="font-family: system-ui; padding: 48px; color: #171717;"><main><h1 style="font-size: 28px; margin: 0 0 8px;">{{ e($page->name) }}</h1><p style="margin: 0; color: #737373;">Generation has not run yet. The preview iframe is ready.</p></main></body></html>'>
        </iframe>
    </div>
</div>
