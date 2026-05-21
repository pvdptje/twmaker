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
@endphp

<div
    class="grid h-full grid-cols-[12rem_1fr]"
    wire:poll.1s
    data-stream-snapshot="{{ base64_encode($streamHtml) }}"
    data-stream-stage="{{ $streamStage }}"
    data-stream-position="{{ $streamPosition }}"
    x-on:generation-started.window="if (!$event.detail?.pageId || $event.detail.pageId === pageId) resetForStage('section_generator')"
    x-data="{
        pageId: @js($page->id),
        open: @js($statusLabel === 'running'),
        html: @js($streamHtml),
        stage: @js($streamStage),
        connected: false,
        socketState: 'connecting',
        terminal: false,
        dismissed: false,
        channel: null,
        lastChunkAt: Date.now(),
        now: Date.now(),
        pollTimer: null,
        observer: null,
        init() {
            this.syncSnapshot();
            this.observer = new MutationObserver(() => this.syncSnapshot());
            this.observer.observe(this.$el, {
                attributes: true,
                attributeFilter: ['data-stream-snapshot', 'data-stream-stage', 'data-stream-position'],
            });
            this.pollTimer = setInterval(() => {
                this.now = Date.now();
            }, 500);
            this.subscribe();
            document.addEventListener('livewire:navigated', () => this.subscribe());
            document.addEventListener('livewire:init', () => this.subscribe());
            window.addEventListener('focus', () => this.subscribe());
        },
        resetForStage(stage = 'section_generator') {
            this.open = true;
            this.dismissed = false;
            this.terminal = false;
            this.html = '';
            this.stage = stage;
            this.lastChunkAt = Date.now();
            this.scrollToBottom();
        },
        syncSnapshot() {
            const encoded = this.$el.dataset.streamSnapshot || '';
            let snapshot = '';

            try {
                snapshot = decodeURIComponent(escape(atob(encoded)));
            } catch (error) {
                try {
                    snapshot = atob(encoded);
                } catch (fallbackError) {
                    snapshot = '';
                }
            }

            const position = Number(this.$el.dataset.streamPosition || snapshot.length);
            const stage = this.$el.dataset.streamStage || this.stage;

            if (position === 0 && this.html !== '') {
                this.html = '';
                this.stage = stage;
                this.lastChunkAt = Date.now();

                return;
            }

            if (stage !== this.stage || snapshot.length > this.html.length) {
                this.html = snapshot;
                this.stage = stage;
                this.lastChunkAt = Date.now();
                this.open = !this.dismissed;
                this.scrollToBottom();
            }
        },
        scrollToBottom() {
            this.$nextTick(() => {
                const code = this.$refs.streamedCode;
                if (code) code.scrollTop = code.scrollHeight;
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
                    const position = Number(event.position ?? this.html.length);

                    this.stage = event.stage || this.stage;

                    if (position < this.html.length) {
                        if (this.html.slice(position, position + chunk.length) === chunk) {
                            return;
                        }

                        this.html = this.html.slice(0, position) + chunk;
                    } else {
                        this.html += chunk;
                    }

                    this.open = !this.dismissed;
                    this.lastChunkAt = Date.now();

                    this.scrollToBottom();
                })
                .listen('.GenerationEventBroadcast', (event) => {
                    if (!event) return;
                    if ((event.kind === 'stage_started' && event.stage === 'section_generator') || (event.kind === 'edit_requested' && event.stage === 'targeted_edit')) {
                        this.resetForStage(event.stage);
                    }
                    if (event.kind === 'generation_completed' || event.kind === 'generation_failed' || event.kind === 'edit_applied' || event.kind === 'edit_rejected') {
                        this.terminal = true;
                    }
                });
        },
        isThinking() {
            return this.open && !this.terminal && this.html !== '' && this.now - this.lastChunkAt > 2500;
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
            x-bind:disabled="open || (html === '' && !@js($statusLabel === 'running'))"
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
        <div class="flex h-[min(44rem,90vh)] w-[min(72rem,94vw)] flex-col overflow-hidden rounded-md border border-cyan-400/30 bg-neutral-950 shadow-2xl shadow-cyan-950/40">
            <div class="flex items-center justify-between border-b border-neutral-800 px-4 py-3">
                <div class="min-w-0">
                    <div class="text-sm font-semibold text-white">Template stream</div>
                    <div class="mt-0.5 text-xs text-neutral-500" x-text="isThinking() ? `${stage} / model is thinking` : `${stage} / live HTML`"></div>
                </div>
                <div class="flex items-center gap-3">
                    <div class="rounded-md border border-amber-300/40 bg-amber-300/10 px-2 py-1 text-xs font-medium text-amber-100" x-show="isThinking()">thinking</div>
                    <div class="rounded-md border border-cyan-400/40 bg-cyan-400/10 px-2 py-1 text-xs font-medium text-cyan-100" x-show="!terminal && !isThinking()">running</div>
                    <button type="button" class="rounded-md border border-neutral-700 px-3 py-1.5 text-xs font-semibold text-neutral-200 hover:border-neutral-500" x-on:click="close()">Hide</button>
                </div>
            </div>
            <div class="border-b border-neutral-800 px-4 py-2 text-xs text-neutral-400" x-show="!terminal && html === ''">
                <span class="inline-flex items-center gap-2">
                    <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-amber-300"></span>
                    <span>Model is thinking before the first HTML arrives.</span>
                </span>
            </div>
            <pre
                x-ref="streamedCode"
                class="min-h-0 flex-1 overflow-auto whitespace-pre-wrap break-words bg-neutral-950 p-4 font-mono text-xs leading-5 text-cyan-50"
                x-text="html"
            ></pre>
        </div>
    </div>
</div>
