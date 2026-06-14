import { readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';
import { JSDOM } from 'jsdom';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
const realtimeScript = readFileSync(resolve(root, 'resources/js/builder-realtime.js'), 'utf8');

function bootRealtime(pageId = 'page_01') {
    const dom = new JSDOM(`<main data-builder-workspace-page-id="${pageId}"></main>`, {
        runScripts: 'dangerously',
        url: 'https://builder.test',
    });
    const handlers = {};

    dom.window.Echo = {
        connector: {
            pusher: {
                connection: {
                    state: 'connected',
                    bind: () => {},
                },
            },
        },
        channel: () => ({
            listen(name, handler) {
                handlers[name] = handler;

                return this;
            },
        }),
        leave: () => {},
    };

    dom.window.eval(realtimeScript);
    dom.window.builderRealtimeSubscribe();

    return {
        handlers,
        window: dom.window,
    };
}

describe('builder realtime bridge', () => {
    it('routes document enhancement streams into the full preview stream', () => {
        const { handlers, window } = bootRealtime();
        const starts = [];
        const streams = [];

        window.addEventListener('section-generation-stream-start', (event) => starts.push(event.detail));
        window.addEventListener('section-generation-stream', (event) => streams.push(event.detail));

        handlers['.GenerationEventBroadcast']({
            page_id: 'page_01',
            kind: 'enhance_requested',
            stage: 'document_enhancer',
            payload: {},
        });

        handlers['.GenerationStreamChunk']({
            page_id: 'page_01',
            stage: 'document_enhancer',
            chunk: '<body><main>Hel',
            position: 0,
            stream: 'html',
        });

        handlers['.GenerationStreamChunk']({
            page_id: 'page_01',
            stage: 'document_enhancer',
            chunk: 'lo</main></body>',
            position: 15,
            stream: 'html',
        });

        expect(starts).toHaveLength(1);
        expect(streams).toEqual([
            { html: '<body><main>Hel' },
            { html: '<body><main>Hello</main></body>' },
        ]);
    });

    it('patches the block in place when the terminal event carries html source', () => {
        const { handlers, window } = bootRealtime();
        const applied = [];
        const finished = [];

        window.addEventListener('targeted-edit-applied', (event) => applied.push(event.detail));
        window.addEventListener('generation-finished', (event) => finished.push(event.detail));

        handlers['.GenerationEventBroadcast']({
            page_id: 'page_01',
            kind: 'edit_requested',
            stage: 'targeted_edit',
            payload: { target_ids: ['block_hero'] },
        });

        handlers['.GenerationEventBroadcast']({
            page_id: 'page_01',
            kind: 'edit_applied',
            stage: 'targeted_edit',
            payload: { target_ids: ['block_hero'], html_source: '<section>Final</section>' },
        });

        expect(applied).toEqual([
            { targetIds: ['block_hero'], html: '<section>Final</section>' },
        ]);
        expect(finished).toEqual([
            { pageId: 'page_01', status: 'valid', incremental: true },
        ]);
    });

    it('reconciles from the server instead of streamed text when html source is dropped', () => {
        const { handlers, window } = bootRealtime();
        const applied = [];
        const cancelled = [];
        const finished = [];

        window.addEventListener('targeted-edit-applied', (event) => applied.push(event.detail));
        window.addEventListener('targeted-edit-stream-cancel', (event) => cancelled.push(event.detail));
        window.addEventListener('generation-finished', (event) => finished.push(event.detail));

        handlers['.GenerationEventBroadcast']({
            page_id: 'page_01',
            kind: 'edit_requested',
            stage: 'targeted_edit',
            payload: { target_ids: ['block_hero'] },
        });

        handlers['.GenerationStreamChunk']({
            page_id: 'page_01',
            stage: 'targeted_edit',
            chunk: '<section>Updated',
            position: 0,
            stream: 'html',
        });

        handlers['.GenerationStreamChunk']({
            page_id: 'page_01',
            stage: 'targeted_edit',
            chunk: '</section>',
            position: 16,
            stream: 'html',
        });

        handlers['.GenerationEventBroadcast']({
            page_id: 'page_01',
            kind: 'edit_applied',
            stage: 'targeted_edit',
            payload: { target_ids: ['block_hero'], html_source_available: true },
        });

        // The raw stream must never be patched in as the final result.
        expect(applied).toEqual([]);
        // The in-progress streamed preview is dropped, and a full authoritative
        // reconcile is requested via a non-incremental generation-finished.
        expect(cancelled).toEqual([{ targetIds: ['block_hero'] }]);
        expect(finished).toEqual([
            { pageId: 'page_01', status: 'valid', incremental: false },
        ]);
    });
});
