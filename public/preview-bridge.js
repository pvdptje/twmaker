(function () {
    let selected = null;
    let selectionOverlay = null;
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
    const navigableLinkSelector = 'a[href], area[href]';

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

    function isBlockRootCandidate(node) {
        if (!(node instanceof Element)) {
            return false;
        }

        return !['LINK', 'META', 'SCRIPT', 'STYLE', 'TEMPLATE', 'TITLE'].includes(node.tagName);
    }

    function nextBlockRootSibling(node) {
        let current = node.nextSibling;

        while (current) {
            if (current.nodeType === Node.ELEMENT_NODE && isBlockRootCandidate(current)) {
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
            const root = marker ? nextBlockRootSibling(walker.currentNode) : null;

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

        selected = null;
        hideSelectionOverlay();
    }

    function selectionOverlayElement() {
        if (selectionOverlay?.isConnected) {
            return selectionOverlay;
        }

        selectionOverlay = document.createElement('div');
        selectionOverlay.setAttribute('aria-hidden', 'true');
        selectionOverlay.dataset.builderSelectionOverlay = 'true';
        Object.assign(selectionOverlay.style, {
            position: 'fixed',
            display: 'none',
            pointerEvents: 'none',
            boxSizing: 'border-box',
            border: '2px solid #06b6d4',
            borderRadius: '6px',
            boxShadow: '0 0 0 2px rgba(8, 145, 178, 0.18), 0 10px 28px rgba(8, 145, 178, 0.28)',
            zIndex: '2147483647',
            transition: 'top 80ms ease, left 80ms ease, width 80ms ease, height 80ms ease'
        });

        (document.body || document.documentElement).appendChild(selectionOverlay);

        return selectionOverlay;
    }

    function hideSelectionOverlay() {
        if (selectionOverlay) {
            selectionOverlay.style.display = 'none';
        }
    }

    function updateSelectionOverlay() {
        if (!selected?.isConnected) {
            hideSelectionOverlay();
            return;
        }

        const rect = selected.getBoundingClientRect();
        if (rect.width <= 0 || rect.height <= 0) {
            hideSelectionOverlay();
            return;
        }

        const viewportWidth = window.innerWidth || document.documentElement.clientWidth || 0;
        const viewportHeight = window.innerHeight || document.documentElement.clientHeight || 0;
        if (rect.right < 0 || rect.bottom < 0 || rect.left > viewportWidth || rect.top > viewportHeight) {
            hideSelectionOverlay();
            return;
        }

        const overlay = selectionOverlayElement();
        overlay.style.display = 'block';
        overlay.style.left = `${rect.left}px`;
        overlay.style.top = `${rect.top}px`;
        overlay.style.width = `${rect.width}px`;
        overlay.style.height = `${rect.height}px`;
    }

    function selectElement(element) {
        clearSelection();
        selected = element;
        selected.classList.add('builder-selected');
        updateSelectionOverlay();
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

    function selectionRoot(editable) {
        return editable.blockRoot || editable.target;
    }

    function cleanOuterHtml(element) {
        const clone = element.cloneNode(true);
        clone.classList?.remove('builder-selected');

        if (clone.getAttribute?.('class') === '') {
            clone.removeAttribute('class');
        }

        return clone.outerHTML;
    }

    function closestElement(from, selector) {
        const clicked = from instanceof Element ? from : from?.parentElement;

        return clicked?.closest(selector) || null;
    }

    function preventPreviewNavigation(event) {
        if (closestElement(event.target, navigableLinkSelector)) {
            event.preventDefault();
        }
    }

    function handlePointerSelection(event, openQuickEdit = false) {
        const editable = editableTarget(event.target);
        if (!editable) {
            return;
        }

        const path = editPath(editable.blockRoot, editable.target);
        const blockId = editable.blockRoot.dataset.builderBlockId || editable.blockRoot.dataset.twBlock || editable.blockRoot.dataset.nodeId;
        if (!blockId || path === null) {
            return;
        }

        if (!editable.target.closest(focusableFormSelector)) {
            event.preventDefault();
        }
        selectElement(selectionRoot(editable));

        const rect = editable.target.getBoundingClientRect();

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
            },
            openQuickEdit: openQuickEdit === true
        }, '*');
    }

    document.addEventListener('click', preventPreviewNavigation, true);
    document.addEventListener('auxclick', preventPreviewNavigation, true);

    document.addEventListener('click', function (event) {
        handlePointerSelection(event, false);
    }, true);

    document.addEventListener('dblclick', function (event) {
        handlePointerSelection(event, true);
    }, true);

    window.addEventListener('message', function (event) {
        if (event.data?.type === 'select-node') {
            clearSelection();

            if (!event.data.nodeId) {
                hideSelectionOverlay();
                return;
            }

            const nextSelected = document.querySelector(nodeSelector(event.data.nodeId));

            if (nextSelected) {
                selectElement(nextSelected);

                if (event.data.scrollIntoView === true && typeof selected.scrollIntoView === 'function') {
                    selected.scrollIntoView({
                        behavior: 'smooth',
                        block: 'center',
                        inline: 'nearest'
                    });
                    window.requestAnimationFrame?.(updateSelectionOverlay);
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
            selectElement(blockRoot?.isConnected ? blockRoot : replacement);

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
    window.addEventListener('resize', updateSelectionOverlay);
    window.addEventListener('scroll', updateSelectionOverlay, true);
})();
