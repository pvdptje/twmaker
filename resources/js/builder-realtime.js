(function () {
    const state = {
        pageId: null,
        channel: null,
        subscribedChannel: null,
        chunks: 0,
        chars: 0,
        html: '',
        output: '',
        connectionBound: false,
        activeTargetedEdit: null,
        realtimeState: 'connecting',
    };

    function workspace() {
        return document.querySelector('[data-builder-workspace-page-id]');
    }

    function emit(name, detail = {}) {
        window.dispatchEvent(new CustomEvent(name, { detail }));
    }

    function setText(selector, value) {
        document.querySelectorAll(selector).forEach((element) => {
            element.textContent = value;
        });
    }

    function appendText(selector, value) {
        document.querySelectorAll(selector).forEach((element) => {
            element.textContent = value;
            element.scrollTop = element.scrollHeight;
        });
    }

    function setRealtimeState(value) {
        state.realtimeState = value;
        setText('[data-realtime-state]', value);
        emit('generation-realtime-status', { state: value });
    }

    function applyChunk(text, chunk, position) {
        if (position < text.length) {
            if (text.slice(position, position + chunk.length) === chunk) {
                return text;
            }

            return text.slice(0, position) + chunk;
        }

        return text + chunk;
    }

    function updateStreamDom(event) {
        const chunk = String(event.chunk || '');
        const stream = String(event.stream || 'html');
        const position = Number(event.position ?? (stream === 'output' ? state.output.length : state.html.length));

        state.chunks += 1;
        state.chars += chunk.length;

        if (stream === 'output') {
            state.output = applyChunk(state.output, chunk, position);
        } else {
            state.html = applyChunk(state.html, chunk, position);
        }

        setText('[data-generation-status]', 'running');
        setText('[data-stream-stage]', event.stage || 'section_generator');
        setText('[data-stream-count]', String(state.chunks));
        setText('[data-stream-chars]', String(state.chars));
        appendText('[data-live-stream-output]', state.output || state.html || 'Waiting for broadcast chunks.');
    }

    function updateEventDom(event) {
        if (!event) return;

        if (event.stage) {
            setText('[data-stream-stage]', event.stage);
        }

        if (event.kind === 'stage_started' || event.kind === 'edit_requested' || event.kind === 'insert_requested' || event.kind === 'enhance_requested') {
            setText('[data-generation-status]', 'running');
        }

        if (event.kind === 'generation_completed' || event.kind === 'edit_applied' || event.kind === 'insert_applied' || event.kind === 'enhance_applied') {
            setText('[data-generation-status]', 'valid');
        }

        if (event.kind === 'generation_failed' || event.kind === 'edit_rejected' || event.kind === 'insert_rejected' || event.kind === 'enhance_rejected') {
            setText('[data-generation-status]', 'error');
        }
    }

    function handleChunk(event) {
        if (!event || String(event.page_id || '') !== String(state.pageId)) return;

        updateStreamDom(event);
        emit('generation-stream-chunk', event);

        if (event.stage === 'targeted_edit' && state.activeTargetedEdit) {
            emit('targeted-edit-stream', {
                targetIds: state.activeTargetedEdit.targetIds,
                html: state.html,
            });
        }

        if (event.stage === 'section_generator' || event.stage === 'section_generator_retry') {
            emit('section-generation-stream', { html: state.html });
        }
    }

    function handleGenerationEvent(event) {
        if (!event || String(event.page_id || '') !== String(state.pageId)) return;

        updateEventDom(event);
        emit('generation-event-received', event);

        if ((event.kind === 'stage_started' && event.stage === 'section_generator')
            || (event.kind === 'edit_requested' && event.stage === 'targeted_edit')
            || (event.kind === 'insert_requested' && event.stage === 'section_inserter')
            || (event.kind === 'enhance_requested' && event.stage === 'document_enhancer')) {
            const detail = { pageId: state.pageId, stage: event.stage };
            emit('generation-started', detail);
            window.Livewire?.dispatch?.('generation-started', detail);
        }

        if (event.kind === 'stage_started' && event.stage === 'section_generator') {
            state.html = '';
            state.output = '';
            emit('section-generation-stream-start', {});
        }

        if (event.kind === 'edit_requested' && event.stage === 'targeted_edit') {
            const targetIds = Array.isArray(event.payload?.target_ids) ? event.payload.target_ids.filter((id) => typeof id === 'string' && id !== '') : [];
            if (targetIds.length > 0) {
                state.activeTargetedEdit = { targetIds };
                state.html = '';
                state.output = '';
                emit('targeted-edit-stream-start', { targetIds });
            }
        }

        if (event.kind === 'insert_requested' && event.stage === 'section_inserter') {
            state.html = '';
            state.output = '';
        }

        if (event.kind === 'enhance_requested' && event.stage === 'document_enhancer') {
            state.html = '';
            state.output = '';
        }

        if (event.kind === 'edit_applied') {
            const targetIds = Array.isArray(event.payload?.target_ids) ? event.payload.target_ids : [];
            const html = typeof event.payload?.html_source === 'string' ? event.payload.html_source : '';
            if (targetIds.length > 0 && html !== '') {
                emit('targeted-edit-applied', { targetIds, html });
            }
        }

        if (event.kind === 'edit_rejected' && state.activeTargetedEdit) {
            emit('targeted-edit-stream-cancel', { targetIds: state.activeTargetedEdit.targetIds });
        }

        if (event.kind === 'edit_applied' || event.kind === 'edit_rejected') {
            state.activeTargetedEdit = null;
        }

        const terminal = {
            generation_completed: ['valid', false],
            generation_failed: ['error', false],
            edit_applied: ['valid', true],
            edit_rejected: ['error', true],
            insert_applied: ['valid', false],
            insert_rejected: ['error', false],
            enhance_applied: ['valid', false],
            enhance_rejected: ['error', false],
        }[event.kind];

        if (!terminal) return;

        const [status, incremental] = terminal;
        const detail = { pageId: state.pageId, status, incremental };
        emit('generation-finished', detail);
        window.Livewire?.dispatch?.('generation-finished', detail);
    }

    function bindConnection() {
        if (state.connectionBound) return;

        const connection = window.Echo?.connector?.pusher?.connection;
        if (!connection) return;

        state.connectionBound = true;
        setRealtimeState(connection.state || 'subscribed');
        connection.bind('state_change', (states) => {
            setRealtimeState(states.current || 'unknown');
        });
        connection.bind('error', () => {
            setRealtimeState('error');
        });
    }

    function subscribe() {
        const root = workspace();
        if (!root) return;

        const pageId = root.dataset.builderWorkspacePageId;
        if (!pageId) return;

        state.pageId = pageId;

        if (!window.Echo) {
            setRealtimeState('waiting');
            window.setTimeout(subscribe, 250);

            return;
        }

        bindConnection();

        const liveState = window.Echo.connector?.pusher?.connection?.state;
        if (liveState) {
            setRealtimeState(liveState);
        }

        const channelName = `pages.${pageId}.generation`;
        if (state.subscribedChannel === channelName) return;

        if (state.subscribedChannel && window.Echo.leave) {
            window.Echo.leave(state.subscribedChannel);
        }

        state.chunks = 0;
        state.chars = 0;
        state.html = '';
        state.output = '';
        setText('[data-stream-count]', '0');
        setText('[data-stream-chars]', '0');
        appendText('[data-live-stream-output]', 'Waiting for broadcast chunks.');

        state.channel = window.Echo.channel(channelName)
            .listen('.GenerationStreamChunk', handleChunk)
            .listen('.GenerationEventBroadcast', handleGenerationEvent);
        state.subscribedChannel = channelName;
        setRealtimeState(window.Echo.connector?.pusher?.connection?.state || 'subscribed');
    }

    window.builderRealtimeSubscribe = subscribe;
    window.builderRealtimeState = () => state.realtimeState;

    document.addEventListener('DOMContentLoaded', subscribe);
    document.addEventListener('livewire:navigated', subscribe);
    document.addEventListener('livewire:init', subscribe);
})();
