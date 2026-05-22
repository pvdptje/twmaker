(function () {
    let selected = null;
    const editableSelector = [
        'a',
        'article',
        'aside',
        'button',
        'div',
        'footer',
        'form',
        'header',
        'h1',
        'h2',
        'h3',
        'h4',
        'h5',
        'h6',
        'img',
        'input',
        'label',
        'li',
        'main',
        'nav',
        'ol',
        'p',
        'section',
        'textarea',
        'ul'
    ].join(',');
    const focusableFormSelector = [
        'input',
        'select',
        'textarea',
        '[contenteditable=""]',
        '[contenteditable="true"]'
    ].join(',');
    const navigableLinkSelector = 'a[href]';

    function cssEscape(value) {
        if (window.CSS && typeof window.CSS.escape === 'function') {
            return window.CSS.escape(value);
        }

        return String(value).replace(/["\\]/g, '\\$&');
    }

    function parseBlockMarker(comment) {
        const text = comment?.nodeValue || '';

        if (!/^\s*tw:block\b/i.test(text)) {
            return null;
        }

        const attrs = {};
        text.replace(/([a-z0-9_-]+)\s*=\s*"([^"]*)"/gi, function (_, key, value) {
            attrs[key] = value;
            return '';
        });

        return attrs.id ? attrs : null;
    }

    function nextElementSibling(node) {
        let current = node.nextSibling;

        while (current) {
            if (current.nodeType === Node.ELEMENT_NODE) {
                return current;
            }

            current = current.nextSibling;
        }

        return null;
    }

    function annotateBlocksFromComments() {
        const walker = document.createTreeWalker(document.body || document, window.NodeFilter.SHOW_COMMENT);

        while (walker.nextNode()) {
            const marker = parseBlockMarker(walker.currentNode);
            const root = marker ? nextElementSibling(walker.currentNode) : null;

            if (!root) {
                continue;
            }

            root.dataset.builderBlockId = marker.id;
            root.dataset.builderBlockType = marker.type || 'block';
            root.dataset.builderBlockLabel = marker.label || marker.type || 'Block';
        }
    }

    function blockSelector(id) {
        const escaped = cssEscape(id);

        return '[data-builder-block-id="' + escaped + '"], [data-tw-block="' + escaped + '"], [data-node-id="' + escaped + '"]';
    }

    function nodeSelector(id) {
        const escaped = cssEscape(id);

        return '[data-node-id="' + escaped + '"], [data-builder-block-id="' + escaped + '"], [data-tw-block="' + escaped + '"]';
    }

    function clearSelection() {
        if (selected) {
            selected.classList.remove('builder-selected');
        }
    }

    function elementChildren(node) {
        return Array.from(node.children || []);
    }

    function editPath(blockRoot, target) {
        const path = [];
        let current = target;

        while (current && current !== blockRoot) {
            const parent = current.parentElement;

            if (!parent) {
                return null;
            }

            path.unshift(elementChildren(parent).indexOf(current));
            current = parent;
        }

        return current === blockRoot ? path : null;
    }

    function parseEditId(editId) {
        if (typeof editId !== 'string') {
            return null;
        }

        const separator = editId.indexOf(':');
        if (separator === -1) {
            return null;
        }

        const blockId = editId.slice(0, separator);
        const path = editId.slice(separator + 1);

        return {
            blockId,
            path: path === '' ? [] : path.split('.').map((value) => Number.parseInt(value, 10))
        };
    }

    function elementAtPath(blockRoot, path) {
        let current = blockRoot;

        for (const index of path) {
            if (!Number.isInteger(index) || index < 0) {
                return null;
            }

            current = elementChildren(current)[index] || null;

            if (!current) {
                return null;
            }
        }

        return current;
    }

    function singleReplacementElement(html) {
        const template = document.createElement('template');
        template.innerHTML = html.trim();

        const elements = elementChildren(template.content);
        const hasText = Array.from(template.content.childNodes).some((node) => {
            return node.nodeType === Node.TEXT_NODE && node.textContent.trim() !== '';
        });

        return elements.length === 1 && !hasText ? elements[0] : null;
    }

    function editableTarget(from) {
        const clicked = from instanceof Element ? from : from?.parentElement;
        const target = clicked?.closest(editableSelector);
        const blockRoot = clicked?.closest('[data-builder-block-id]') || clicked?.closest('[data-tw-block]') || clicked?.closest('[data-node-id]');

        if (!target || !blockRoot || !blockRoot.contains(target)) {
            return null;
        }

        return { target, blockRoot };
    }

    function cleanOuterHtml(element) {
        const clone = element.cloneNode(true);
        clone.classList?.remove('builder-selected');

        if (clone.getAttribute?.('class') === '') {
            clone.removeAttribute('class');
        }

        return clone.outerHTML;
    }

    document.addEventListener('click', function (event) {
        const editable = editableTarget(event.target);
        if (!editable) {
            return;
        }

        const path = editPath(editable.blockRoot, editable.target);
        const blockId = editable.blockRoot.dataset.builderBlockId || editable.blockRoot.dataset.twBlock || editable.blockRoot.dataset.nodeId;
        if (!blockId || path === null) {
            return;
        }

        if (editable.target.closest(navigableLinkSelector) || !editable.target.closest(focusableFormSelector)) {
            event.preventDefault();
        }
        event.stopPropagation();

        clearSelection();
        selected = editable.target;
        selected.classList.add('builder-selected');

        const rect = selected.getBoundingClientRect();

        window.parent.postMessage({
            type: 'builder:node-selected',
            nodeId: editable.target.dataset.nodeId || editable.target.dataset.builderBlockId || blockId,
            nodeType: editable.target.dataset.nodeType || editable.target.dataset.builderBlockType || null,
            quickEdit: {
                editId: blockId + ':' + path.join('.'),
                blockId,
                tagName: editable.target.tagName.toLowerCase(),
                outerHTML: cleanOuterHtml(editable.target),
                rect: {
                    x: rect.x,
                    y: rect.y,
                    width: rect.width,
                    height: rect.height
                },
                click: {
                    x: event.clientX,
                    y: event.clientY
                }
            }
        }, '*');
    }, true);

    window.addEventListener('message', function (event) {
        if (event.data?.type === 'select-node') {
            clearSelection();

            if (!event.data.nodeId) {
                selected = null;
                return;
            }

            selected = document.querySelector(nodeSelector(event.data.nodeId));

            if (selected) {
                selected.classList.add('builder-selected');

                if (event.data.scrollIntoView === true && typeof selected.scrollIntoView === 'function') {
                    selected.scrollIntoView({
                        behavior: 'smooth',
                        block: 'center',
                        inline: 'nearest'
                    });
                }
            }

            return;
        }

        if (event.data?.type === 'replace-quick-edit') {
            const target = parseEditId(event.data.editId);
            if (!target || typeof event.data.html !== 'string') {
                return;
            }

            const blockRoot = document.querySelector(blockSelector(target.blockId));
            const element = blockRoot ? elementAtPath(blockRoot, target.path) : null;
            const replacement = singleReplacementElement(event.data.html);

            if (!element || !replacement) {
                return;
            }

            element.replaceWith(replacement);
            clearSelection();
            selected = replacement;
            selected.classList.add('builder-selected');

            return;
        }

        if (event.data?.type !== 'replace-subtree') {
            return;
        }

        const target = document.querySelector(nodeSelector(event.data.nodeId));
        if (!target || typeof event.data.html !== 'string') {
            return;
        }

        target.outerHTML = event.data.html;
    });

    annotateBlocksFromComments();
})();
