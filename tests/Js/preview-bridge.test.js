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
        const { document, messages, window } = bootPreview(`<body>
            <!-- tw:block id="block_hero" type="hero" label="Hero" -->
            <section>
                <h1>Ship pages</h1>
                <div><p>Fast</p></div>
            </section>
            <!-- /tw:block -->
        </body>`);

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
        const { document, window } = bootPreview(`<body>
            <!-- tw:block id="block_hero" type="hero" label="Hero" -->
            <section>
                <h1>Ship pages</h1>
                <p class="mt-4">Fast</p>
            </section>
            <!-- /tw:block -->
        </body>`);

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
        const { document, window } = bootPreview(`<body>
            <main>
                <!-- tw:block id="block_hero" type="hero" label="Hero" -->
                <section>Hero</section>
                <!-- /tw:block -->
                <!-- tw:block id="block_pricing" type="pricing" label="Pricing" -->
                <section>Pricing</section>
                <!-- /tw:block -->
            </main>
        </body>`);
        const pricing = document.querySelector('[data-builder-block-id="block_pricing"]');
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

    it('allows generated form fields to receive focus while reporting selection', () => {
        const { document, messages, window } = bootPreview(`<body>
            <!-- tw:block id="block_contact" type="contact" label="Contact" -->
            <section>
                <label>Message<textarea rows="4">Hello</textarea></label>
            </section>
            <!-- /tw:block -->
        </body>`);
        const textarea = document.querySelector('textarea');
        const event = new window.MouseEvent('click', {
            bubbles: true,
            cancelable: true,
        });

        textarea.dispatchEvent(event);

        expect(event.defaultPrevented).toBe(false);
        expect(textarea.classList.contains('builder-selected')).toBe(true);
        expect(messages[0].payload).toMatchObject({
            type: 'builder:node-selected',
            quickEdit: {
                blockId: 'block_contact',
                tagName: 'textarea',
            },
        });
    });

    it('prevents generated links from navigating the preview frame while reporting selection', () => {
        const { document, messages, window } = bootPreview(`<body>
            <!-- tw:block id="block_nav" type="navigation" label="Navigation" -->
            <nav>
                <a href="#about">About</a>
            </nav>
            <!-- /tw:block -->
        </body>`);
        const link = document.querySelector('a');
        const event = new window.MouseEvent('click', {
            bubbles: true,
            cancelable: true,
        });

        link.dispatchEvent(event);

        expect(event.defaultPrevented).toBe(true);
        expect(window.location.href).toBe('https://preview.test/');
        expect(link.classList.contains('builder-selected')).toBe(true);
        expect(messages[0].payload).toMatchObject({
            type: 'builder:node-selected',
            quickEdit: {
                blockId: 'block_nav',
                tagName: 'a',
            },
        });
    });
});
