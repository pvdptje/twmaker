<div
    class="grid h-full grid-cols-[12rem_1fr]"
    x-data="{
        statusLabel: @js($statusLabel),
        activeStage: @js($activeStage),
        events: @js($events),
        socketState: window.builderRealtimeState?.() || 'connecting',
        streamStage: @js($activeStage),
        htmlPreview: '',
        outputPreview: '',
        pendingPreviewChunks: {},
        previewFlushTimer: null,
        maxRows: 80,
        applyChunk(text, chunk, position) {
            if (position < text.length) {
                if (text.slice(position, position + chunk.length) === chunk) return text;

                return text.slice(0, position) + chunk;
            }

            return text + chunk;
        },
        updateRealtimeStatus(event) {
            this.socketState = event.detail?.state || this.socketState;
        },
        appendChunk(event) {
            const detail = event.detail || {};
            if (!detail.chunk) return;

            const chunk = String(detail.chunk);
            const stream = String(detail.stream || 'html');
            const position = Number(detail.position ?? (stream === 'output' ? this.outputPreview.length : this.htmlPreview.length));
            const pending = this.pendingPreviewChunks[stream];

            this.streamStage = detail.stage || this.streamStage;
            this.statusLabel = 'running';

            if (pending && pending.position + pending.chunk.length === position) {
                pending.chunk += chunk;
            } else {
                this.flushPreviewChunks();
                this.pendingPreviewChunks[stream] = { chunk, position };
            }

            this.schedulePreviewFlush();
        },
        schedulePreviewFlush() {
            if (this.previewFlushTimer) return;

            this.previewFlushTimer = setTimeout(() => {
                this.previewFlushTimer = null;
                this.flushPreviewChunks();
            }, 75);
        },
        flushPreviewChunks() {
            Object.entries(this.pendingPreviewChunks).forEach(([stream, pending]) => {
                if (!pending?.chunk) return;

                const position = Number(pending.position ?? (stream === 'output' ? this.outputPreview.length : this.htmlPreview.length));

                if (stream === 'output') {
                    this.outputPreview = this.applyChunk(this.outputPreview, pending.chunk, position);
                } else {
                    this.htmlPreview = this.applyChunk(this.htmlPreview, pending.chunk, position);
                }
            });

            this.pendingPreviewChunks = {};
            this.$nextTick(() => {
                const output = this.$root.querySelector('[data-live-stream-output]');
                if (output) output.scrollTop = output.scrollHeight;
            });
        },
        statusClass() {
            return {
                running: 'border-cyan-400/40 bg-cyan-400/10 text-cyan-100',
                valid: 'border-emerald-400/40 bg-emerald-400/10 text-emerald-100',
                error: 'border-red-400/40 bg-red-400/10 text-red-100',
            }[this.statusLabel] || 'border-neutral-700 bg-neutral-800 text-neutral-300';
        },
        addEvent(event) {
            if (!event?.id || this.events.some((row) => row.id === event.id)) return;

            this.events.unshift({
                id: event.id,
                kind: event.kind || '',
                stage: event.stage || '',
                level: event.level || 'info',
                summary: event.summary || '',
                occurred_at: event.occurred_at || null,
            });
            this.events = this.events.slice(0, this.maxRows);
        },
        updateStatus(event) {
            if (!event) return;
            if (event.stage) this.activeStage = event.stage;

            if (event.kind === 'stage_started' || event.kind === 'edit_requested' || event.kind === 'insert_requested' || event.kind === 'remove_requested' || event.kind === 'enhance_requested') {
                this.statusLabel = 'running';
            }

            if (event.kind === 'generation_completed' || event.kind === 'edit_applied' || event.kind === 'insert_applied' || event.kind === 'remove_applied' || event.kind === 'enhance_applied') {
                this.statusLabel = 'valid';
            }

            if (event.kind === 'generation_failed' || event.kind === 'edit_rejected' || event.kind === 'insert_rejected' || event.kind === 'remove_rejected' || event.kind === 'enhance_rejected') {
                this.statusLabel = 'error';
            }
        },
        eventClass(event) {
            if (event.level === 'success') return 'border-emerald-400/25 bg-gradient-to-r from-emerald-400/10 via-neutral-950 to-neutral-950';
            if (event.level === 'error') return 'border-red-400/30 bg-gradient-to-r from-red-500/10 via-neutral-950 to-neutral-950';
            if (this.isRunningEvent(event)) return 'border-cyan-400/25 bg-gradient-to-r from-cyan-400/10 via-neutral-950 to-neutral-950';

            return 'border-neutral-800 bg-neutral-950';
        },
        iconClass(event) {
            if (event.level === 'success') return 'border-emerald-400/40 bg-emerald-400/15 text-emerald-200';
            if (event.level === 'error') return 'border-red-400/40 bg-red-400/15 text-red-200';

            return 'border-cyan-400/30 bg-cyan-400/10 text-cyan-200';
        },
        isRunningEvent(event) {
            return event?.kind === 'edit_requested' || event?.kind === 'insert_requested' || event?.kind === 'remove_requested' || event?.kind === 'enhance_requested' || String(event?.kind || '').endsWith('started');
        },
    }"
    x-on:generation-event-received.window="addEvent($event.detail); updateStatus($event.detail)"
    x-on:generation-stream-chunk.window="appendChunk($event)"
    x-on:generation-realtime-status.window="updateRealtimeStatus($event)"
>
    <div class="border-r border-neutral-800 p-4">
        <div class="text-sm font-semibold text-white">Activity</div>
        <div class="mt-2 rounded-md border px-2 py-1 text-xs font-medium" data-generation-status :class="statusClass()" x-text="statusLabel">{{ $statusLabel }}</div>
        <div class="mt-2 text-xs text-neutral-500">
            Realtime: <span data-realtime-state x-text="socketState">connecting</span>
        </div>

        <template x-if="statusLabel === 'running'">
            <div>
                <div class="mt-3 overflow-hidden rounded-full bg-neutral-800">
                    <div class="h-1.5 w-1/2 animate-pulse rounded-full bg-gradient-to-r from-cyan-300 via-violet-300 to-emerald-300"></div>
                </div>
                <div class="mt-2 text-xs text-neutral-500" data-stream-stage x-text="activeStage"></div>
            </div>
        </template>

    </div>

    <div class="grid min-h-0 grid-cols-[minmax(0,1fr)_minmax(18rem,32rem)]">
        <div class="min-h-0 overflow-y-auto p-3">
        <template x-for="event in events" :key="event.id">
            <div data-generation-event-row class="mb-2 rounded-md border px-3 py-2 shadow-sm" :class="eventClass(event)">
                <div class="flex items-start gap-3">
                    <div class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full border" :class="iconClass(event)">
                        <template x-if="event.level === 'success'">
                            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M16.704 5.29a1 1 0 0 1 .006 1.414l-7.25 7.32a1 1 0 0 1-1.421 0L3.29 9.23a1 1 0 1 1 1.42-1.408l4.04 4.08 6.54-6.606a1 1 0 0 1 1.414-.006Z" clip-rule="evenodd" />
                            </svg>
                        </template>
                        <template x-if="event.level === 'error'">
                            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" clip-rule="evenodd" />
                            </svg>
                        </template>
                        <template x-if="event.level !== 'success' && event.level !== 'error'">
                            <span class="h-2 w-2 rounded-full bg-current" :class="{ 'animate-pulse': isRunningEvent(event) }"></span>
                        </template>
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="text-xs text-neutral-500"><span x-text="event.stage"></span> / <span x-text="event.kind"></span></div>
                        <div class="text-sm text-neutral-200" x-text="event.summary"></div>
                    </div>
                </div>
            </div>
        </template>

        <div x-show="events.length === 0" class="flex h-full items-center justify-center text-sm text-neutral-500">No generation events yet.</div>
        </div>

        <aside class="flex min-h-0 flex-col border-l border-neutral-800 bg-neutral-950">
            <div class="border-b border-neutral-800 px-3 py-2">
                <div class="text-xs font-semibold text-neutral-300">Live stream</div>
                <div class="mt-1 text-xs text-neutral-500" data-stream-stage x-text="streamStage"></div>
            </div>
            <pre
                data-live-stream-output
                class="min-h-0 flex-1 overflow-auto whitespace-pre-wrap break-words p-3 font-mono text-[11px] leading-5 text-cyan-50"
                x-text="outputPreview || htmlPreview || 'Waiting for broadcast chunks.'"
            >Waiting for broadcast chunks.</pre>
        </aside>
    </div>
</div>
