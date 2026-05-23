<div class="flex h-full flex-col">
    <div class="border-b border-neutral-800 p-4">
        <div class="text-sm font-semibold text-white">Inspector</div>
        <div class="mt-1 text-xs text-neutral-500">{{ $selectedNodeId ?: 'No selection' }}</div>
        @if (count($selectedBlockIds) > 0)
            <div class="mt-1 text-xs text-cyan-300">{{ count($selectedBlockIds) }} multi-edit selection{{ count($selectedBlockIds) === 1 ? '' : 's' }}</div>
        @endif
    </div>
    <div class="min-h-0 flex-1 overflow-y-auto">
        <livewire:builder.inspector.node-summary.node-summary :selected-node-id="$selectedNodeId" />
        <livewire:builder.inspector.edit-form.edit-form :page="$page" :selected-node-id="$selectedNodeId" :selected-block-ids="$selectedBlockIds" />
        <section
            class="border-b border-neutral-800 p-4"
            x-data="{
                pageId: @js($page->id),
                output: '',
                stage: 'section_generator',
                running: false,
                applyChunk(text, chunk, position) {
                    if (position < text.length) {
                        if (text.slice(position, position + chunk.length) === chunk) return text;

                        return text.slice(0, position) + chunk;
                    }

                    return text + chunk;
                },
                scrollToBottom() {
                    this.$nextTick(() => {
                        if (this.$refs.output) this.$refs.output.scrollTop = this.$refs.output.scrollHeight;
                    });
                },
                reset(event) {
                    if (event.detail?.pageId && String(event.detail.pageId) !== String(this.pageId)) return;

                    this.output = '';
                    this.stage = event.detail?.stage || 'section_generator';
                    this.running = true;
                    this.scrollToBottom();
                },
                finish(event) {
                    if (event.detail?.pageId && String(event.detail.pageId) !== String(this.pageId)) return;

                    this.running = false;
                },
                append(event) {
                    const detail = event.detail || {};
                    if (String(detail.page_id || '') !== String(this.pageId) || detail.stream !== 'output' || !detail.chunk) return;

                    this.stage = detail.stage || this.stage;
                    this.output = this.applyChunk(this.output, String(detail.chunk), Number(detail.position ?? this.output.length));
                    this.running = true;
                    this.scrollToBottom();
                },
            }"
            x-on:generation-started.window="reset($event)"
            x-on:generation-finished.window="finish($event)"
            x-on:generation-stream-chunk.window="append($event)"
        >
            <div class="flex items-center justify-between gap-3">
                <div>
                    <div class="text-xs font-semibold uppercase tracking-normal text-neutral-500">LLM output</div>
                    <div class="mt-1 text-xs text-neutral-500" x-text="stage"></div>
                </div>
                <div
                    class="rounded-md border border-cyan-400/40 bg-cyan-400/10 px-2 py-1 text-xs font-medium text-cyan-100"
                    x-show="running"
                    x-cloak
                >
                    streaming
                </div>
            </div>
            <pre
                x-ref="output"
                class="mt-3 max-h-64 min-h-36 overflow-auto whitespace-pre-wrap break-words rounded-md border border-neutral-800 bg-neutral-950 p-3 font-mono text-[11px] leading-5 text-neutral-200"
                x-text="output || 'No model output yet.'"
            ></pre>
        </section>
    </div>
    <div class="border-t border-neutral-800 p-4">
        <div class="text-xs font-semibold uppercase tracking-normal text-neutral-500">Tokens</div>
        @forelse ($usageTotals as $model => $usage)
            <div class="mt-2 rounded-md border border-neutral-800 bg-neutral-950 px-2 py-1.5">
                <div class="truncate text-xs font-medium text-neutral-200" title="{{ $model }}">{{ $model }}</div>
                <div class="mt-1 text-xs text-neutral-500">
                    {{ number_format($usage['total']) }} total
                </div>
            </div>
        @empty
            <div class="mt-2 text-xs text-neutral-500">No token usage yet.</div>
        @endforelse
    </div>
</div>
