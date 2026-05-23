@php
    $statusClass = match ($statusLabel) {
        'running' => 'border-cyan-400/40 bg-cyan-400/10 text-cyan-100',
        'valid' => 'border-emerald-400/40 bg-emerald-400/10 text-emerald-100',
        'error' => 'border-red-400/40 bg-red-400/10 text-red-100',
        default => 'border-neutral-700 bg-neutral-800 text-neutral-300',
    };

    $streamHtml = (string) ($streamSnapshot['html'] ?? '');
    $streamStage = (string) ($streamSnapshot['stage'] ?? 'section_generator');
    $streamPosition = (int) ($streamSnapshot['position'] ?? strlen($streamHtml));
    $outputText = (string) ($outputSnapshot['output'] ?? '');
    $outputStage = (string) ($outputSnapshot['stage'] ?? $streamStage);
    $outputPosition = (int) ($outputSnapshot['position'] ?? strlen($outputText));
@endphp

<div
    class="grid h-full grid-cols-[12rem_1fr]"
    wire:poll.1s
    data-stream-snapshot="{{ base64_encode($streamHtml) }}"
    data-stream-stage="{{ $streamStage }}"
    data-stream-position="{{ $streamPosition }}"
    data-output-snapshot="{{ base64_encode($outputText) }}"
    data-output-stage="{{ $outputStage }}"
    data-output-position="{{ $outputPosition }}"
    data-targeted-edit-patch="{{ base64_encode(json_encode($targetedEditPatch, JSON_THROW_ON_ERROR)) }}"
    data-status-label="{{ $statusLabel }}"
    x-on:generation-started.window="if (!$event.detail?.pageId || $event.detail.pageId === pageId) resetForStage('section_generator')"
    x-data="{
        pageId: @js($page->id),
        statusLabel: @js($statusLabel),
        open: @js($statusLabel === 'running'),
        html: @js($streamHtml),
        output: @js($outputText),
        targetHtml: @js($streamHtml),
        targetOutput: @js($outputText),
        stage: @js($streamStage),
        outputStage: @js($outputStage),
        connected: false,
        socketState: 'connecting',
        terminal: false,
        dismissed: false,
        channel: null,
        lastChunkAt: Date.now(),
        now: Date.now(),
        pollTimer: null,
        observer: null,
        revealFrame: null,
        lastRevealAt: 0,
        revealCarry: 0,
        lastTargetedPatchKey: null,
        baseCharsPerSecond: 180,
        maxFrameChars: 48,
        init() {
            this.syncSnapshot();
            this.syncTargetedEditPatch();
            this.observer = new MutationObserver(() => this.syncSnapshot());
            this.observer.observe(this.$el, {
                attributes: true,
                attributeFilter: ['data-stream-snapshot', 'data-stream-stage', 'data-stream-position', 'data-output-snapshot', 'data-output-stage', 'data-output-position', 'data-targeted-edit-patch', 'data-status-label'],
            });
            this.pollTimer = setInterval(() => {
                this.now = Date.now();
            }, 500);
            this.subscribe();
            document.addEventListener('livewire:navigated', () => this.subscribe());
            document.addEventListener('livewire:init', () => this.subscribe());
            window.addEventListener('focus', () => this.subscribe());
        },
        syncStatus() {
            const nextStatus = this.$el.dataset.statusLabel || this.statusLabel;
            if (nextStatus === this.statusLabel) return;

            this.statusLabel = nextStatus;

            if (nextStatus === 'running') {
                this.terminal = false;
                this.open = !this.dismissed;

                return;
            }

            if (nextStatus === 'valid') {
                this.terminal = true;
                this.open = false;
                this.dismissed = false;

                return;
            }

            if (nextStatus === 'error') {
                this.terminal = true;
            }
        },
        resetForStage(stage = 'section_generator') {
            this.open = true;
            this.dismissed = false;
            this.terminal = false;
            this.html = '';
            this.output = '';
            this.targetHtml = '';
            this.targetOutput = '';
            this.stage = stage;
            this.outputStage = stage;
            this.lastChunkAt = Date.now();
            this.cancelReveal();
            this.scrollToBottom();
        },
        decodeSnapshot(encoded) {
            try {
                return decodeURIComponent(escape(atob(encoded)));
            } catch (error) {
                try {
                    return atob(encoded);
                } catch (fallbackError) {
                    return '';
                }
            }
        },
        syncSnapshot() {
            this.syncStatus();
            const snapshot = this.decodeSnapshot(this.$el.dataset.streamSnapshot || '');
            const outputSnapshot = this.decodeSnapshot(this.$el.dataset.outputSnapshot || '');
            this.syncTargetedEditPatch();

            const position = Number(this.$el.dataset.streamPosition || snapshot.length);
            const stage = this.$el.dataset.streamStage || this.stage;
            const outputPosition = Number(this.$el.dataset.outputPosition || outputSnapshot.length);
            const outputStage = this.$el.dataset.outputStage || this.outputStage;

            if (position === 0 && this.targetHtml !== '') {
                this.targetHtml = '';
                this.html = '';
                this.stage = stage;
                this.lastChunkAt = Date.now();
            }

            if (stage !== this.stage) {
                this.targetHtml = snapshot;
                this.html = snapshot;
                this.cancelReveal();
                this.stage = stage;
                this.lastChunkAt = Date.now();
                this.open = !this.dismissed && !this.terminal;
                this.scrollToBottom();
            } else if (snapshot.length > this.targetHtml.length) {
                this.targetHtml = snapshot;
                this.stage = stage;
                this.lastChunkAt = Date.now();
                this.open = !this.dismissed && !this.terminal;
                this.queueReveal();
            }

            if (outputPosition === 0 && this.targetOutput !== '') {
                this.targetOutput = '';
                this.output = '';
                this.outputStage = outputStage;
                this.lastChunkAt = Date.now();
            }

            if (outputStage !== this.outputStage) {
                this.targetOutput = outputSnapshot;
                this.output = outputSnapshot;
                this.cancelReveal();
                this.outputStage = outputStage;
                this.lastChunkAt = Date.now();
                this.open = !this.dismissed && !this.terminal;
                this.scrollToBottom();
            } else if (outputSnapshot.length > this.targetOutput.length) {
                this.targetOutput = outputSnapshot;
                this.outputStage = outputStage;
                this.lastChunkAt = Date.now();
                this.open = !this.dismissed && !this.terminal;
                this.queueReveal();
            }
        },
        syncTargetedEditPatch() {
            const raw = this.decodeSnapshot(this.$el.dataset.targetedEditPatch || '');
            if (!raw) return;

            let patch = null;
            try {
                patch = JSON.parse(raw);
            } catch (error) {
                return;
            }

            const key = patch?.eventId || `${(patch?.targetIds || []).join(',')}:${patch?.html?.length || 0}`;
            if (!patch?.html || !Array.isArray(patch?.targetIds) || patch.targetIds.length === 0 || key === this.lastTargetedPatchKey) return;

            this.lastTargetedPatchKey = key;
            this.terminal = true;
            this.open = false;
            this.dismissed = false;
            window.dispatchEvent(new CustomEvent('targeted-edit-applied', {
                detail: {
                    targetIds: patch.targetIds,
                    html: patch.html,
                },
            }));
            if (window.Livewire?.dispatch) {
                window.Livewire.dispatch('generation-finished', {
                    pageId: this.pageId,
                    status: 'valid',
                    incremental: true,
                });
            }
        },
        cancelReveal() {
            if (this.revealFrame) cancelAnimationFrame(this.revealFrame);
            this.revealFrame = null;
            this.lastRevealAt = 0;
            this.revealCarry = 0;
        },
        queueReveal() {
            if (this.revealFrame) return;

            this.revealFrame = requestAnimationFrame((timestamp) => this.revealText(timestamp));
        },
        revealText(timestamp) {
            this.revealFrame = null;

            if (!this.lastRevealAt) this.lastRevealAt = timestamp;

            const elapsed = Math.max(16, timestamp - this.lastRevealAt);
            const backlog = Math.max(
                this.targetHtml.length - this.html.length,
                this.targetOutput.length - this.output.length,
            );
            const charsPerSecond = this.baseCharsPerSecond + Math.min(1800, backlog * 3);

            this.lastRevealAt = timestamp;
            this.revealCarry += (elapsed / 1000) * charsPerSecond;

            const count = Math.max(1, Math.min(this.maxFrameChars, Math.floor(this.revealCarry)));
            this.revealCarry = Math.max(0, this.revealCarry - count);

            let changed = false;

            if (this.html.length < this.targetHtml.length) {
                this.html += this.targetHtml.slice(this.html.length, this.html.length + count);
                changed = true;
            } else if (this.html.length > this.targetHtml.length) {
                this.html = this.targetHtml;
                changed = true;
            }

            if (this.output.length < this.targetOutput.length) {
                this.output += this.targetOutput.slice(this.output.length, this.output.length + count);
                changed = true;
            } else if (this.output.length > this.targetOutput.length) {
                this.output = this.targetOutput;
                changed = true;
            }

            if (changed) this.scrollToBottom();

            if (this.html.length < this.targetHtml.length || this.output.length < this.targetOutput.length) {
                this.queueReveal();
            } else {
                this.lastRevealAt = 0;
                this.revealCarry = 0;
            }
        },
        applyChunk(text, chunk, position) {
            if (position < text.length) {
                if (text.slice(position, position + chunk.length) === chunk) {
                    return text;
                }

                return text.slice(0, position) + chunk;
            }

            return text + chunk;
        },
        scrollToBottom() {
            this.$nextTick(() => {
                const code = this.$refs.streamedCode;
                if (code) code.scrollTop = code.scrollHeight;
                const output = this.$refs.outputCode;
                if (output) output.scrollTop = output.scrollHeight;
            });
        },
        subscribe() {
            if (this.connected) return;

            if (!window.Echo) {
                this.socketState = 'waiting';
                setTimeout(() => this.subscribe(), 250);

                return;
            }

            this.connected = true;
            this.socketState = 'subscribed';
            if (window.Echo.connector?.pusher?.connection) {
                this.socketState = window.Echo.connector.pusher.connection.state || this.socketState;
                window.Echo.connector.pusher.connection.bind('state_change', (states) => {
                    this.socketState = states.current || this.socketState;
                });
            }
            this.channel = window.Echo.channel(`pages.${this.pageId}.generation`)
                .listen('.GenerationStreamChunk', (event) => {
                    const eventStage = String(event?.stage || '');
                    if (!event?.chunk || (!eventStage.startsWith('section_generator') && !eventStage.startsWith('targeted_edit'))) return;

                    const chunk = String(event.chunk);
                    const stream = String(event.stream || 'html');

                    if (stream === 'output') {
                        const position = Number(event.position ?? this.targetOutput.length);
                        this.outputStage = event.stage || this.outputStage;
                        const nextOutput = this.applyChunk(this.targetOutput, chunk, position);
                        if (nextOutput === this.targetOutput) return;

                        this.targetOutput = nextOutput;
                        this.open = !this.dismissed;
                        this.lastChunkAt = Date.now();
                        this.queueReveal();

                        return;
                    }

                    const position = Number(event.position ?? this.targetHtml.length);
                    this.stage = event.stage || this.stage;
                    const nextHtml = this.applyChunk(this.targetHtml, chunk, position);
                    if (nextHtml === this.targetHtml) return;

                    this.targetHtml = nextHtml;
                    this.open = !this.dismissed;
                    this.lastChunkAt = Date.now();

                    this.queueReveal();
                })
                .listen('.GenerationEventBroadcast', (event) => {
                    if (!event) return;
                    if ((event.kind === 'stage_started' && event.stage === 'section_generator') || (event.kind === 'edit_requested' && event.stage === 'targeted_edit')) {
                        this.resetForStage(event.stage);
                    }
                    if (event.kind === 'generation_completed' || event.kind === 'generation_failed' || event.kind === 'edit_applied' || event.kind === 'edit_rejected') {
                        this.terminal = true;
                        if (event.kind === 'generation_completed' || event.kind === 'edit_applied') {
                            this.open = false;
                            this.dismissed = false;
                        }
                        if (event.kind === 'edit_applied') {
                            window.dispatchEvent(new CustomEvent('targeted-edit-applied', {
                                detail: {
                                    targetIds: Array.isArray(event.payload?.target_ids) ? event.payload.target_ids : [],
                                    html: event.payload?.html_source || '',
                                },
                            }));
                        }
                        if (window.Livewire?.dispatch) {
                            window.Livewire.dispatch('generation-finished', {
                                pageId: this.pageId,
                                status: (event.kind === 'generation_completed' || event.kind === 'edit_applied') ? 'valid' : 'error',
                                incremental: event.kind === 'edit_applied',
                            });
                        }
                    }
                });
        },
        isThinking() {
            return this.open && !this.terminal && (this.targetHtml === '' || this.now - this.lastChunkAt > 2500);
        },
        close() {
            this.dismissed = true;
            this.open = false;
        },
        reopen() {
            this.dismissed = false;
            this.open = true;
            this.scrollToBottom();
        },
    }"
>
    <div class="border-r border-neutral-800 p-4">
        <div class="text-sm font-semibold text-white">Stream</div>
        <div class="mt-2 rounded-md border px-2 py-1 text-xs font-medium {{ $statusClass }}">{{ $statusLabel }}</div>

        @if ($statusLabel === 'running')
            <div class="mt-3 overflow-hidden rounded-full bg-neutral-800">
                <div class="h-1.5 w-1/2 animate-pulse rounded-full bg-gradient-to-r from-cyan-300 via-violet-300 to-emerald-300"></div>
            </div>
            <div class="mt-2 flex items-center gap-1 text-xs text-cyan-200">
                <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-cyan-300"></span>
                <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-violet-300 [animation-delay:120ms]"></span>
                <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-emerald-300 [animation-delay:240ms]"></span>
            </div>
        @endif
        <button
            type="button"
            class="mt-4 w-full rounded-md border border-cyan-400/40 bg-cyan-400/10 px-3 py-2 text-xs font-semibold text-cyan-100 transition-colors hover:border-cyan-300 hover:bg-cyan-400/15 disabled:cursor-not-allowed disabled:border-neutral-800 disabled:bg-neutral-950 disabled:text-neutral-600"
            x-on:click="reopen()"
            x-bind:disabled="targetHtml === '' && !@js($statusLabel === 'running')"
        >
            Open stream
        </button>
    </div>
    <livewire:builder.stream-panel.event-list.event-list :page="$page" />

    <div
        x-cloak
        x-show="open"
        x-transition.opacity
        class="fixed inset-0 z-50 flex items-center justify-center bg-neutral-950/75 p-6 backdrop-blur-sm"
    >
        <div class="flex h-[min(44rem,90vh)] w-[min(84rem,94vw)] flex-col overflow-hidden rounded-md border border-cyan-400/30 bg-neutral-950 shadow-2xl shadow-cyan-950/40">
            <div class="flex items-center justify-between border-b border-neutral-800 px-4 py-3">
                <div class="min-w-0">
                    <div class="text-sm font-semibold text-white">Template stream</div>
                    <div class="mt-0.5 text-xs text-neutral-500" x-text="isThinking() ? `${stage} / model activity` : `${stage} / live HTML`"></div>
                </div>
                <div class="flex items-center gap-3">
                    <div class="rounded-md border border-amber-300/40 bg-amber-300/10 px-2 py-1 text-xs font-medium text-amber-100" x-show="isThinking()">thinking</div>
                    <div class="rounded-md border border-cyan-400/40 bg-cyan-400/10 px-2 py-1 text-xs font-medium text-cyan-100" x-show="!terminal && !isThinking()">running</div>
                    <button type="button" class="rounded-md border border-neutral-700 px-3 py-1.5 text-xs font-semibold text-neutral-200 hover:border-neutral-500" x-on:click="close()">Hide</button>
                </div>
            </div>
            <div class="grid min-h-0 flex-1 grid-cols-1 md:grid-cols-[minmax(0,1fr)_19rem]">
                <div class="flex min-h-0 flex-col border-r border-neutral-800">
                    <div class="border-b border-neutral-800 px-4 py-2 text-xs text-neutral-400" x-show="!terminal && targetHtml === ''">
                        <span class="inline-flex items-center gap-2">
                            <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-amber-300"></span>
                            <span>Waiting for the first HTML tokens.</span>
                        </span>
                    </div>
                    <pre
                        x-ref="streamedCode"
                        class="min-h-0 flex-1 overflow-auto whitespace-pre-wrap break-words bg-neutral-950 p-4 font-mono text-xs leading-5 text-cyan-50"
                        x-text="html"
                    ></pre>
                </div>
                <aside class="flex min-h-0 flex-col bg-neutral-900/70">
                    <div class="border-b border-neutral-800 px-3 py-3">
                        <div class="text-xs font-semibold text-neutral-300">LLM output</div>
                        <div class="mt-1 text-xs text-neutral-500" x-text="`${outputStage} / provider-visible stream`"></div>
                    </div>
                    <div class="border-b border-neutral-800 px-3 py-2 text-xs text-neutral-500" x-show="!terminal && targetOutput === ''">
                        <div class="inline-flex items-center gap-2">
                            <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-amber-300"></span>
                            <span>Waiting for visible model output.</span>
                        </div>
                    </div>
                    <pre
                        x-ref="outputCode"
                        class="min-h-0 flex-1 overflow-auto whitespace-pre-wrap break-words p-3 font-mono text-[11px] leading-5 text-neutral-200"
                        x-text="output"
                    ></pre>
                </aside>
            </div>
        </div>
    </div>
</div>
