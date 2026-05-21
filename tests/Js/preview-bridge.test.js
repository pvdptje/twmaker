import { readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';
import { JSDOM } from 'jsdom';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
const bridgeScript = readFileSync(resolve(root, 'public/preview-bridge.js'), 'utf8');

function bootPreview(html) {
    const dom = new JSDOM(html, {
        runScripts: 'dangerously',
        url: 'https://preview.test',
    });
    const messages = [];

    dom.window.parent.postMessage = (payload, targetOrigin) => {
        messages.push({ payload, targetOrigin });
    };

    dom.window.eval(bridgeScript);

    return {
        document: dom.window.document,
        messages,
        window: dom.window,
    };
}

describe('preview bridge', () => {
    it('selects the nearest rendered node and reports it to the parent', () => {
        const { document, messages, window } = bootPreview(`
            <section data-node-id="sec_01h00000000000000000000000" data-node-type="hero">
                <h1 data-node-id="node_01h00000000000000000000001" data-node-type="heading">
                    <span>Ship pages</span>
                </h1>
                <p data-node-id="node_01h00000000000000000000002" data-node-type="text">Fast</p>
            </section>
        `);

        document.querySelector('span').dispatchEvent(new window.MouseEvent('click', {
            bubbles: true,
            cancelable: true,
        }));

        expect(document.querySelector('h1').classList.contains('builder-selected')).toBe(true);
        expect(document.querySelector('section').classList.contains('builder-selected')).toBe(false);
        expect(messages).toHaveLength(1);
        expect(messages[0].targetOrigin).toBe('*');
        expect(messages[0].payload).toMatchObject({
            type: 'builder:node-selected',
            nodeId: 'node_01h00000000000000000000001',
            nodeType: 'heading',
            quickEdit: {
                editId: 'node_01h00000000000000000000001:',
                blockId: 'node_01h00000000000000000000001',
                tagName: 'h1',
            },
        });
        expect(messages[0].payload.quickEdit.outerHTML).toContain('<h1');
        expect(messages[0].payload.quickEdit.outerHTML).toContain('<span>Ship pages</span>');

        document.querySelector('p').dispatchEvent(new window.MouseEvent('click', {
            bubbles: true,
            cancelable: true,
        }));

        expect(document.querySelector('h1').classList.contains('builder-selected')).toBe(false);
        expect(document.querySelector('p').classList.contains('builder-selected')).toBe(true);
    });

    it('reports a stable edit path inside a marked block', () => {
        const { document, messages, window } = bootPreview(`
            <section data-node-id="block_hero" data-node-type="hero" data-tw-block="block_hero">
                <h1>Ship pages</h1>
                <div><p>Fast</p></div>
            </section>
        `);

        document.querySelector('p').dispatchEvent(new window.MouseEvent('click', {
            bubbles: true,
            cancelable: true,
            clientX: 12,
            clientY: 24,
        }));

        expect(messages[0].payload).toMatchObject({
            type: 'builder:node-selected',
            nodeId: 'block_hero',
            quickEdit: {
                editId: 'block_hero:1.0',
                blockId: 'block_hero',
                tagName: 'p',
                outerHTML: '<p>Fast</p>',
                click: {
                    x: 12,
                    y: 24,
                },
            },
        });
    });

    it('replaces a rendered subtree by node id', () => {
        const { document, window } = bootPreview(`
            <main>
                <div data-node-id="node_01h00000000000000000000001" data-node-type="text">Old copy</div>
            </main>
        `);

        window.dispatchEvent(new window.MessageEvent('message', {
            data: {
                type: 'replace-subtree',
                nodeId: 'node_01h00000000000000000000001',
                html: '<p data-node-id="node_01h00000000000000000000002" data-node-type="text">New copy</p>',
            },
        }));

        expect(document.querySelector('[data-node-id="node_01h00000000000000000000001"]')).toBeNull();
        expect(document.querySelector('[data-node-id="node_01h00000000000000000000002"]').textContent).toBe('New copy');
    });

    it('replaces a quick-edited element by edit path without reloading the preview', () => {
        const { document, window } = bootPreview(`
            <section data-node-id="block_hero" data-node-type="hero" data-tw-block="block_hero">
                <h1>Ship pages</h1>
                <p class="mt-4">Fast</p>
            </section>
        `);

        window.dispatchEvent(new window.MessageEvent('message', {
            data: {
                type: 'replace-quick-edit',
                editId: 'block_hero:1',
                html: '<p class="mt-8 text-lg text-neutral-600">Better website copy here.</p>',
            },
        }));

        expect(document.querySelector('h1').textContent).toBe('Ship pages');
        expect(document.querySelector('p').className).toContain('mt-8');
        expect(document.querySelector('p').classList.contains('builder-selected')).toBe(true);
        expect(document.querySelector('p').textContent).toBe('Better website copy here.');
    });

    it('applies a selection sent by the parent frame', () => {
        const { document, window } = bootPreview(`
            <main>
                <h1 data-node-id="node_01h00000000000000000000001" data-node-type="heading">Heading</h1>
                <p data-node-id="node_01h00000000000000000000002" data-node-type="text">Copy</p>
            </main>
        `);

        window.dispatchEvent(new window.MessageEvent('message', {
            data: {
                type: 'select-node',
                nodeId: 'node_01h00000000000000000000002',
            },
        }));

        expect(document.querySelector('h1').classList.contains('builder-selected')).toBe(false);
        expect(document.querySelector('p').classList.contains('builder-selected')).toBe(true);

        window.dispatchEvent(new window.MessageEvent('message', {
            data: {
                type: 'select-node',
                nodeId: null,
            },
        }));

        expect(document.querySelector('p').classList.contains('builder-selected')).toBe(false);
    });

    it('scrolls a parent-selected node into view when requested', () => {
        const { document, window } = bootPreview(`
            <main>
                <section data-node-id="block_hero" data-node-type="hero">Hero</section>
                <section data-node-id="block_pricing" data-node-type="pricing">Pricing</section>
            </main>
        `);
        const pricing = document.querySelector('[data-node-id="block_pricing"]');
        const calls = [];
        pricing.scrollIntoView = (options) => calls.push(options);

        window.dispatchEvent(new window.MessageEvent('message', {
            data: {
                type: 'select-node',
                nodeId: 'block_pricing',
                scrollIntoView: true,
            },
        }));

        expect(pricing.classList.contains('builder-selected')).toBe(true);
        expect(calls).toEqual([{
            behavior: 'smooth',
            block: 'center',
            inline: 'nearest',
        }]);
    });
});
