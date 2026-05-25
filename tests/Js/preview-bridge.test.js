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

    it('draws a non-interactive overlay around the selected element', () => {
        const { document, window } = bootPreview(`
            <section data-node-id="sec_01h00000000000000000000000" data-node-type="hero">
                <h1 data-node-id="node_01h00000000000000000000001" data-node-type="heading">Ship pages</h1>
            </section>
        `);
        const heading = document.querySelector('h1');
        heading.getBoundingClientRect = () => ({
            x: 20,
            y: 30,
            left: 20,
            top: 30,
            right: 220,
            bottom: 78,
            width: 200,
            height: 48,
            toJSON: () => {},
        });

        heading.dispatchEvent(new window.MouseEvent('click', {
            bubbles: true,
            cancelable: true,
        }));

        const overlay = document.querySelector('[data-builder-selection-overlay="true"]');
        expect(overlay).not.toBeNull();
        expect(overlay.style.pointerEvents).toBe('none');
        expect(overlay.style.display).toBe('block');
        expect(overlay.style.left).toBe('20px');
        expect(overlay.style.top).toBe('30px');
        expect(overlay.style.width).toBe('200px');
        expect(overlay.style.height).toBe('48px');
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
        expect(document.querySelector('section').classList.contains('builder-selected')).toBe(true);
        expect(document.querySelector('p').classList.contains('builder-selected')).toBe(false);
    });

    it('treats tw:group wrappers as selectable parents while child blocks stay selectable', () => {
        const { document, messages, window } = bootPreview(`<body>
            <!-- tw:group id="block_features" type="features" label="Features" -->
            <section>
                <h2>Features</h2>
                <!-- tw:block id="block_card" type="card" label="Card" -->
                <article><p>Fast</p></article>
                <!-- /tw:block -->
            </section>
            <!-- /tw:group -->
        </body>`);

        document.querySelector('h2').dispatchEvent(new window.MouseEvent('click', {
            bubbles: true,
            cancelable: true,
        }));

        expect(document.querySelector('section').dataset.builderBlockId).toBe('block_features');
        expect(document.querySelector('section').dataset.builderMarkerKind).toBe('group');
        expect(messages[0].payload).toMatchObject({
            type: 'builder:node-selected',
            nodeId: 'block_features',
            quickEdit: {
                editId: 'block_features:0',
                blockId: 'block_features',
                tagName: 'h2',
            },
        });

        document.querySelector('p').dispatchEvent(new window.MouseEvent('click', {
            bubbles: true,
            cancelable: true,
        }));

        expect(messages[1].payload).toMatchObject({
            nodeId: 'block_card',
            quickEdit: {
                editId: 'block_card:0',
                blockId: 'block_card',
            },
        });
    });

    it('draws the overlay around the marked block root when a child is clicked', () => {
        const { document, window } = bootPreview(`<body>
            <!-- tw:block id="block_hero" type="hero" label="Hero" -->
            <section>
                <h1>Ship pages</h1>
            </section>
            <!-- /tw:block -->
        </body>`);
        const section = document.querySelector('section');
        section.getBoundingClientRect = () => ({
            x: 10,
            y: 12,
            left: 10,
            top: 12,
            right: 410,
            bottom: 252,
            width: 400,
            height: 240,
            toJSON: () => {},
        });

        document.querySelector('h1').dispatchEvent(new window.MouseEvent('click', {
            bubbles: true,
            cancelable: true,
        }));

        const overlay = document.querySelector('[data-builder-selection-overlay="true"]');
        expect(section.classList.contains('builder-selected')).toBe(true);
        expect(document.querySelector('h1').classList.contains('builder-selected')).toBe(false);
        expect(overlay.style.left).toBe('10px');
        expect(overlay.style.top).toBe('12px');
        expect(overlay.style.width).toBe('400px');
        expect(overlay.style.height).toBe('240px');
    });

    it('does not pin the overlay to the viewport edge when selection scrolls away', () => {
        const { document, window } = bootPreview(`<body>
            <!-- tw:block id="block_hero" type="hero" label="Hero" -->
            <section>
                <h1>Ship pages</h1>
            </section>
            <!-- /tw:block -->
        </body>`);
        const section = document.querySelector('section');
        section.getBoundingClientRect = () => ({
            x: 10,
            y: -260,
            left: 10,
            top: -260,
            right: 410,
            bottom: -20,
            width: 400,
            height: 240,
            toJSON: () => {},
        });

        document.querySelector('h1').dispatchEvent(new window.MouseEvent('click', {
            bubbles: true,
            cancelable: true,
        }));

        const overlay = document.querySelector('[data-builder-selection-overlay="true"]');
        expect(overlay?.style.display).not.toBe('block');
        expect(overlay?.style.top).not.toBe('0px');
    });

    it('uses the first rendered element as the block root when a block starts with style', () => {
        const { document, messages, window } = bootPreview(`<body>
            <!-- tw:block id="block_hero" type="hero" label="Hero" -->
            <style>.hero { color: red; }</style>
            <section class="hero">
                <h1>Ship pages</h1>
            </section>
            <!-- /tw:block -->
        </body>`);

        document.querySelector('h1').dispatchEvent(new window.MouseEvent('click', {
            bubbles: true,
            cancelable: true,
        }));

        expect(document.querySelector('style').dataset.builderBlockId).toBeUndefined();
        expect(document.querySelector('section').dataset.builderBlockId).toBe('block_hero');
        expect(document.querySelector('section').classList.contains('builder-selected')).toBe(true);
        expect(document.querySelector('h1').classList.contains('builder-selected')).toBe(false);
        expect(messages[0].payload).toMatchObject({
            type: 'builder:node-selected',
            nodeId: 'block_hero',
            quickEdit: {
                editId: 'block_hero:0',
                blockId: 'block_hero',
                tagName: 'h1',
            },
        });
    });

    it('only asks the parent to open quick edit on double click', () => {
        const { document, messages, window } = bootPreview(`<body>
            <!-- tw:block id="block_hero" type="hero" label="Hero" -->
            <section><h1>Ship pages</h1></section>
            <!-- /tw:block -->
        </body>`);
        const heading = document.querySelector('h1');

        heading.dispatchEvent(new window.MouseEvent('click', {
            bubbles: true,
            cancelable: true,
        }));

        heading.dispatchEvent(new window.MouseEvent('dblclick', {
            bubbles: true,
            cancelable: true,
        }));

        expect(messages).toHaveLength(2);
        expect(messages[0].payload).toMatchObject({
            type: 'builder:node-selected',
            openQuickEdit: false,
        });
        expect(messages[1].payload).toMatchObject({
            type: 'builder:node-selected',
            openQuickEdit: true,
            quickEdit: {
                blockId: 'block_hero',
                tagName: 'h1',
                outerHTML: '<h1>Ship pages</h1>',
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
        expect(document.querySelector('section').classList.contains('builder-selected')).toBe(true);
        expect(document.querySelector('p').classList.contains('builder-selected')).toBe(false);
        expect(document.querySelector('p').textContent).toBe('Better website copy here.');
    });

    it('pulses original blocks until streamed html arrives, then swaps to a placeholder', () => {
        const { document, window } = bootPreview(`<body>
            <main>
                <!-- tw:block id="block_hero" type="hero" label="Hero" -->
                <section><h1>Old hero</h1></section>
                <!-- /tw:block -->
                <!-- tw:block id="block_features" type="features" label="Features" -->
                <section><p>Old features</p></section>
                <!-- /tw:block -->
                <!-- tw:block id="block_footer" type="footer" label="Footer" -->
                <footer>Footer</footer>
                <!-- /tw:block -->
            </main>
        </body>`);

        window.dispatchEvent(new window.MessageEvent('message', {
            data: { type: 'stream-block-range-start', targetIds: ['block_hero', 'block_features'] },
        }));

        const hero = document.querySelector('[data-builder-block-id="block_hero"]');
        const features = document.querySelector('[data-builder-block-id="block_features"]');
        expect(document.querySelector('#builder-streaming-styles')).not.toBeNull();
        expect(document.querySelector('[data-builder-stream-placeholder="true"]')).toBeNull();
        expect(hero.classList.contains('builder-stream-pending')).toBe(true);
        expect(features.classList.contains('builder-stream-pending')).toBe(true);
        expect(hero.style.display).toBe('');
        expect(features.style.display).toBe('');
        expect(document.querySelector('[data-builder-block-id="block_footer"]').style.display).toBe('');

        window.dispatchEvent(new window.MessageEvent('message', {
            data: {
                type: 'stream-block-range-update',
                targetIds: ['block_hero', 'block_features'],
                html: "\n",
            },
        }));

        expect(document.querySelector('[data-builder-stream-placeholder="true"]')).toBeNull();
        expect(hero.classList.contains('builder-stream-pending')).toBe(true);
        expect(features.classList.contains('builder-stream-pending')).toBe(true);

        window.dispatchEvent(new window.MessageEvent('message', {
            data: {
                type: 'stream-block-range-update',
                targetIds: ['block_hero', 'block_features'],
                html: '<!-- tw:block id="block_hero" type="story" label="Story" --><article><h2>Streaming...',
            },
        }));

        const placeholder = document.querySelector('[data-builder-stream-placeholder="true"]');
        expect(placeholder).not.toBeNull();
        expect(hero.classList.contains('builder-stream-pending')).toBe(false);
        expect(features.classList.contains('builder-stream-pending')).toBe(false);
        expect(hero.style.display).toBe('none');
        expect(features.style.display).toBe('none');
        expect(placeholder.innerHTML).toContain('Streaming...');
        expect(placeholder.querySelector('article').dataset.builderBlockId).toBe('block_hero');

        window.dispatchEvent(new window.MessageEvent('message', {
            data: {
                type: 'replace-block-range',
                targetIds: ['block_hero', 'block_features'],
                html: `
                    <!-- tw:block id="block_hero" type="story" label="Story" -->
                    <article><h2>Final story</h2></article>
                    <!-- /tw:block -->
                `,
            },
        }));

        expect(document.querySelector('[data-builder-stream-placeholder="true"]')).toBeNull();
        expect(document.querySelector('h1')).toBeNull();
        expect(document.querySelector('p')).toBeNull();
        expect(document.querySelector('article').dataset.builderBlockId).toBe('block_hero');
        expect(document.querySelector('article').textContent.trim()).toBe('Final story');
        expect(document.querySelector('footer').textContent).toBe('Footer');
    });

    it('restores the original blocks when streaming is cancelled', () => {
        const { document, window } = bootPreview(`<body>
            <main>
                <!-- tw:block id="block_hero" type="hero" label="Hero" -->
                <section style="display: flex"><h1>Original</h1></section>
                <!-- /tw:block -->
            </main>
        </body>`);

        window.dispatchEvent(new window.MessageEvent('message', {
            data: { type: 'stream-block-range-start', targetIds: ['block_hero'] },
        }));

        expect(document.querySelector('[data-builder-stream-placeholder="true"]')).toBeNull();
        expect(document.querySelector('[data-builder-block-id="block_hero"]').classList.contains('builder-stream-pending')).toBe(true);
        expect(document.querySelector('[data-builder-block-id="block_hero"]').style.display).toBe('flex');

        window.dispatchEvent(new window.MessageEvent('message', {
            data: { type: 'stream-block-range-cancel', targetIds: ['block_hero'] },
        }));

        expect(document.querySelector('[data-builder-stream-placeholder="true"]')).toBeNull();
        expect(document.querySelector('[data-builder-block-id="block_hero"]').classList.contains('builder-stream-pending')).toBe(false);
        expect(document.querySelector('[data-builder-block-id="block_hero"]').style.display).toBe('flex');
        expect(document.querySelector('h1').textContent).toBe('Original');
    });

    it('replaces targeted block ranges without reloading the preview', () => {
        const { document, window } = bootPreview(`<body>
            <main>
                <!-- tw:block id="block_hero" type="hero" label="Hero" -->
                <section><h1>Old hero</h1></section>
                <!-- /tw:block -->
                <!-- tw:block id="block_features" type="features" label="Features" -->
                <section><p>Old features</p></section>
                <!-- /tw:block -->
                <!-- tw:block id="block_footer" type="footer" label="Footer" -->
                <footer>Footer</footer>
                <!-- /tw:block -->
            </main>
        </body>`);

        window.dispatchEvent(new window.MessageEvent('message', {
            data: {
                type: 'replace-block-range',
                targetIds: ['block_hero', 'block_features'],
                html: `
                    <!-- tw:block id="block_hero" type="story" label="Story" -->
                    <article><h2>New story</h2></article>
                    <!-- /tw:block -->
                `,
            },
        }));

        expect(document.querySelector('h1')).toBeNull();
        expect(document.querySelector('p')).toBeNull();
        expect(document.querySelector('article').dataset.builderBlockId).toBe('block_hero');
        expect(document.querySelector('article').classList.contains('builder-selected')).toBe(true);
        expect(document.querySelector('footer').textContent).toBe('Footer');
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
        expect(document.querySelector('section').classList.contains('builder-selected')).toBe(true);
        expect(textarea.classList.contains('builder-selected')).toBe(false);
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
        expect(document.querySelector('nav').classList.contains('builder-selected')).toBe(true);
        expect(link.classList.contains('builder-selected')).toBe(false);
        expect(messages[0].payload).toMatchObject({
            type: 'builder:node-selected',
            quickEdit: {
                blockId: 'block_nav',
                tagName: 'a',
            },
        });
    });

    it('prevents unmarked preview links from navigating', () => {
        const { document, messages, window } = bootPreview(`<body>
            <main>
                <a href="#about">About</a>
            </main>
        </body>`);
        const link = document.querySelector('a');
        const event = new window.MouseEvent('click', {
            bubbles: true,
            cancelable: true,
        });

        link.dispatchEvent(event);

        expect(event.defaultPrevented).toBe(true);
        expect(window.location.href).toBe('https://preview.test/');
        expect(messages).toHaveLength(0);
    });

    it('prevents link navigation without blocking generated link click handlers', () => {
        const { document, window } = bootPreview(`<body>
            <!-- tw:block id="block_nav" type="navigation" label="Navigation" -->
            <nav>
                <a href="#about">About</a>
            </nav>
            <!-- /tw:block -->
        </body>`);
        const link = document.querySelector('a');
        let clicked = 0;

        link.addEventListener('click', () => {
            clicked += 1;
        });

        const event = new window.MouseEvent('click', {
            bubbles: true,
            cancelable: true,
        });

        link.dispatchEvent(event);

        expect(event.defaultPrevented).toBe(true);
        expect(clicked).toBe(1);
        expect(window.location.href).toBe('https://preview.test/');
    });

    it('does not block generated click handlers from running', () => {
        const { document, messages, window } = bootPreview(`<body>
            <!-- tw:block id="block_nav" type="navigation" label="Navigation" -->
            <nav>
                <button type="button">Menu</button>
            </nav>
            <!-- /tw:block -->
        </body>`);
        const button = document.querySelector('button');
        let clicked = 0;

        button.addEventListener('click', () => {
            clicked += 1;
        });

        button.dispatchEvent(new window.MouseEvent('click', {
            bubbles: true,
            cancelable: true,
        }));

        expect(clicked).toBe(1);
        expect(document.querySelector('nav').classList.contains('builder-selected')).toBe(true);
        expect(button.classList.contains('builder-selected')).toBe(false);
        expect(messages[0].payload).toMatchObject({
            type: 'builder:node-selected',
            openQuickEdit: false,
            quickEdit: {
                blockId: 'block_nav',
                tagName: 'button',
            },
        });
    });
});
