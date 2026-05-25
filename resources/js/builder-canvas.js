import { EditorView, basicSetup } from 'codemirror';
import { html } from '@codemirror/lang-html';
import { oneDark } from '@codemirror/theme-one-dark';

(function () {
    window.builderQuickEdit = window.builderQuickEdit || {
        editId: null,
        visible: false,
        editorView: null,
    };

    const quickEditorTheme = EditorView.theme({
        '&': {
            minHeight: '14rem',
            backgroundColor: '#0a0a0a',
            color: '#f5f5f5',
            fontSize: '0.75rem',
        },
        '.cm-scroller': {
            minHeight: '14rem',
            maxHeight: '24rem',
            overflow: 'auto',
            fontFamily: 'ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace',
            lineHeight: '1.25rem',
        },
        '.cm-content': {
            padding: '0.75rem',
        },
        '.cm-gutters': {
            backgroundColor: '#0a0a0a',
            borderRightColor: '#262626',
            color: '#737373',
        },
        '.cm-activeLineGutter, .cm-activeLine': {
            backgroundColor: '#171717',
        },
        '&.cm-focused': {
            outline: 'none',
        },
        '&.cm-focused .cm-cursor': {
            borderLeftColor: '#67e8f9',
        },
        '&.cm-focused .cm-selectionBackground, .cm-selectionBackground, ::selection': {
            backgroundColor: '#155e75',
        },
    }, { dark: true });

    function frame() {
        return document.getElementById('builder-preview-frame');
    }

    function syncPreviewSelection(scrollIntoView = false) {
        const previewFrame = frame();

        if (!previewFrame?.contentWindow) {
            return;
        }

        previewFrame.contentWindow.postMessage({
            type: 'select-node',
            nodeId: previewFrame.dataset.selectedNodeId || null,
            scrollIntoView: scrollIntoView === true,
        }, '*');
    }

    function quickEditorElements() {
        return {
            stage: document.getElementById('builder-canvas-stage'),
            panel: document.getElementById('builder-quick-editor'),
            tag: document.getElementById('builder-quick-editor-tag'),
            target: document.getElementById('builder-quick-editor-target'),
            textarea: document.getElementById('builder-quick-editor-html'),
            editorHost: document.getElementById('builder-quick-editor-code'),
            error: document.getElementById('builder-quick-editor-error'),
            close: document.getElementById('builder-quick-editor-close'),
            cancel: document.getElementById('builder-quick-editor-cancel'),
            copy: document.getElementById('builder-quick-editor-copy'),
            save: document.getElementById('builder-quick-editor-save'),
        };
    }

    function currentEditorValue() {
        const { textarea } = quickEditorElements();
        const view = window.builderQuickEdit.editorView;

        if (view) {
            return view.state.doc.toString();
        }

        return textarea?.value || '';
    }

    function ensureCodeEditor() {
        const { editorHost, textarea } = quickEditorElements();

        if (!editorHost || !textarea) {
            return null;
        }

        const existingView = window.builderQuickEdit.editorView;
        if (existingView && existingView.dom.parentElement === editorHost) {
            return existingView;
        }

        existingView?.destroy();

        window.builderQuickEdit.editorView = new EditorView({
            doc: textarea.value,
            parent: editorHost,
            extensions: [
                basicSetup,
                html(),
                oneDark,
                quickEditorTheme,
                EditorView.lineWrapping,
                EditorView.updateListener.of((update) => {
                    if (update.docChanged) {
                        textarea.value = update.state.doc.toString();
                    }
                }),
            ],
        });

        return window.builderQuickEdit.editorView;
    }

    function setQuickEditorValue(value) {
        const { textarea } = quickEditorElements();

        if (textarea) {
            textarea.value = value;
        }

        const view = ensureCodeEditor();

        if (!view) {
            return;
        }

        view.dispatch({
            changes: {
                from: 0,
                to: view.state.doc.length,
                insert: value,
            },
            selection: {
                anchor: 0,
            },
        });

        view.focus();
    }

    async function formatQuickEditHtml(value) {
        try {
            const [{ default: prettier }, { default: htmlPlugin }] = await Promise.all([
                import('prettier/standalone'),
                import('prettier/plugins/html'),
            ]);

            return (await prettier.format(value, {
                parser: 'html',
                plugins: [htmlPlugin],
                printWidth: 100,
            })).trim();
        } catch {
            return value;
        }
    }

    function hideQuickEditor() {
        const { panel, error } = quickEditorElements();
        window.builderQuickEdit.editId = null;
        window.builderQuickEdit.visible = false;
        panel?.classList.add('hidden');
        error?.classList.add('hidden');
        resetQuickEditorCopyButton();
    }

    function positionQuickEditor(quickEdit) {
        const { stage, panel } = quickEditorElements();
        const previewFrame = frame();

        if (!stage || !panel || !previewFrame) {
            return;
        }

        const stageRect = stage.getBoundingClientRect();
        const frameRect = previewFrame.getBoundingClientRect();
        const rect = quickEdit?.rect || { x: 24, y: 24, width: 0, height: 0 };
        const panelWidth = panel.offsetWidth || 544;
        const panelHeight = panel.offsetHeight || 360;
        const preferredLeft = frameRect.left - stageRect.left + rect.x + Math.min(rect.width, 24) + 12;
        const preferredTop = frameRect.top - stageRect.top + rect.y;
        const maxLeft = Math.max(24, stage.clientWidth - panelWidth - 24);
        const maxTop = Math.max(24, stage.clientHeight - panelHeight - 24);

        panel.style.left = `${Math.max(24, Math.min(preferredLeft, maxLeft))}px`;
        panel.style.top = `${Math.max(24, Math.min(preferredTop, maxTop))}px`;
    }

    async function showQuickEditor(quickEdit) {
        const { panel, tag, target, textarea, error } = quickEditorElements();

        if (!panel || !textarea || !quickEdit?.editId || typeof quickEdit.outerHTML !== 'string') {
            return;
        }

        const editId = quickEdit.editId;
        const rawHtml = quickEdit.outerHTML;
        window.builderQuickEdit.editId = quickEdit.editId;
        window.builderQuickEdit.visible = true;
        tag.textContent = `<${quickEdit.tagName || 'element'}>`;
        target.textContent = quickEdit.blockId || '';
        error.textContent = '';
        error.classList.add('hidden');
        resetQuickEditorCopyButton();
        panel.classList.remove('hidden');
        setQuickEditorValue(rawHtml);
        positionQuickEditor(quickEdit);

        const formattedHtml = await formatQuickEditHtml(rawHtml);

        if (window.builderQuickEdit.editId === editId && currentEditorValue() === rawHtml) {
            setQuickEditorValue(formattedHtml);
        }
    }

    function resetQuickEditorSaveButton() {
        const { save } = quickEditorElements();

        if (!save) {
            return;
        }

        save.disabled = false;
        save.textContent = 'Save';
    }

    function resetQuickEditorCopyButton() {
        const { copy } = quickEditorElements();

        if (!copy) {
            return;
        }

        copy.disabled = false;
        copy.textContent = 'Copy';
    }

    function replaceQuickEditedElement(editId, htmlSource) {
        const previewFrame = frame();

        if (!previewFrame?.contentWindow || !editId || typeof htmlSource !== 'string') {
            return;
        }

        previewFrame.contentWindow.postMessage({
            type: 'replace-quick-edit',
            editId,
            html: htmlSource,
        }, '*');
    }

    function replaceTargetedBlocks(targetIds, htmlSource) {
        const previewFrame = frame();

        if (!previewFrame?.contentWindow || !Array.isArray(targetIds) || targetIds.length === 0 || typeof htmlSource !== 'string') {
            return;
        }

        previewFrame.contentWindow.postMessage({
            type: 'replace-block-range',
            targetIds,
            html: htmlSource,
        }, '*');
    }

    let targetedStreamState = null;
    const streamRenderIntervalMs = 120;

    function nowMs() {
        return (window.performance?.now && window.performance.now()) || Date.now();
    }

    function scheduleStreamRender(state, render) {
        if (!state) return;

        const elapsed = nowMs() - state.lastRenderAt;
        if (state.lastRenderAt === 0 || elapsed >= streamRenderIntervalMs) {
            render();
            return;
        }

        if (state.renderTimer) return;

        state.renderTimer = window.setTimeout(() => {
            state.renderTimer = null;
            render();
        }, streamRenderIntervalMs - elapsed);
    }

    function clearTargetedStreamTimer() {
        if (targetedStreamState?.renderTimer) {
            window.clearTimeout(targetedStreamState.renderTimer);
        }
    }

    function startStreamingTargetedBlocks(targetIds) {
        const previewFrame = frame();

        if (!previewFrame?.contentWindow || !Array.isArray(targetIds) || targetIds.length === 0) {
            return;
        }

        clearTargetedStreamTimer();
        targetedStreamState = {
            targetIds,
            pendingHtml: '',
            renderTimer: null,
            lastRenderAt: 0,
        };

        previewFrame.contentWindow.postMessage({
            type: 'stream-block-range-start',
            targetIds,
        }, '*');
    }

    function renderStreamingTargetedBlocks() {
        const previewFrame = frame();
        const state = targetedStreamState;

        if (!previewFrame?.contentWindow || !state || !Array.isArray(state.targetIds) || state.targetIds.length === 0) {
            return;
        }

        state.lastRenderAt = nowMs();
        previewFrame.contentWindow.postMessage({
            type: 'stream-block-range-update',
            targetIds: state.targetIds,
            html: state.pendingHtml,
        }, '*');
    }

    function updateStreamingTargetedBlocks(targetIds, htmlSource) {
        if (!Array.isArray(targetIds) || targetIds.length === 0 || typeof htmlSource !== 'string') {
            return;
        }

        targetedStreamState = targetedStreamState || {
            targetIds,
            pendingHtml: '',
            renderTimer: null,
            lastRenderAt: 0,
        };
        targetedStreamState.targetIds = targetIds;
        targetedStreamState.pendingHtml = htmlSource;

        scheduleStreamRender(targetedStreamState, renderStreamingTargetedBlocks);
    }

    function cancelStreamingTargetedBlocks(targetIds) {
        const previewFrame = frame();

        if (!previewFrame?.contentWindow) {
            return;
        }

        clearTargetedStreamTimer();
        targetedStreamState = null;

        previewFrame.contentWindow.postMessage({
            type: 'stream-block-range-cancel',
            targetIds: Array.isArray(targetIds) ? targetIds : [],
        }, '*');
    }

    const sectionStreamShell = `<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Generating preview...</title>
<link rel="stylesheet" href="/preview.css">
<script src="https://cdn.tailwindcss.com"></script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<style>
body { margin: 0; background: #fff; color: #171717; font-family: ui-sans-serif, system-ui, sans-serif; }
[data-builder-section-stream-root]:empty::before {
  content: 'Generating page...';
  display: flex;
  min-height: 100vh;
  align-items: center;
  justify-content: center;
  color: #737373;
  font-size: 0.875rem;
}
</style>
</head>
<body>
<div data-builder-section-stream-root></div>
</body>
</html>`;

    let sectionStreamState = null;

    function frameRoot() {
        const previewFrame = frame();
        const doc = previewFrame?.contentDocument;
        return doc?.querySelector('[data-builder-section-stream-root]') || null;
    }

    function startStreamingSection() {
        const previewFrame = frame();
        if (!previewFrame) return;

        sectionStreamState = {
            active: true,
            pendingHtml: '',
            lastRender: '',
            renderTimer: null,
            lastRenderAt: 0,
            waitingForLoad: false,
        };
        previewFrame.dataset.canvasBound = 'false';
        previewFrame.srcdoc = sectionStreamShell;
    }

    function extractStreamingBody(html) {
        if (typeof html !== 'string' || html === '') return '';
        const bodyMatch = html.match(/<body\b[^>]*>/i);
        if (!bodyMatch) {
            return /<\s*(?:!doctype|html|head)\b/i.test(html) ? '' : html;
        }
        const after = html.slice(bodyMatch.index + bodyMatch[0].length);
        const closeMatch = after.match(/<\s*\/\s*body\s*>/i);
        return closeMatch ? after.slice(0, closeMatch.index) : after;
    }

    function renderStreamingSection() {
        if (!sectionStreamState?.active) return;

        const previewFrame = frame();
        const doc = previewFrame?.contentDocument;
        if (!doc) return;

        if (doc.readyState === 'loading') {
            sectionStreamState.lastRenderAt = nowMs();

            if (!sectionStreamState.waitingForLoad) {
                sectionStreamState.waitingForLoad = true;
                doc.addEventListener('DOMContentLoaded', () => {
                    if (sectionStreamState) {
                        sectionStreamState.waitingForLoad = false;
                    }

                    renderStreamingSection();
                }, { once: true });
            }

            return;
        }

        const body = extractStreamingBody(sectionStreamState.pendingHtml || '');
        if (body === sectionStreamState.lastRender) return;
        sectionStreamState.lastRender = body;
        sectionStreamState.lastRenderAt = nowMs();

        const root = doc.querySelector('[data-builder-section-stream-root]');
        if (!root) return;

        root.innerHTML = body;
    }

    function updateStreamingSection(html) {
        if (!sectionStreamState?.active) return;

        sectionStreamState.pendingHtml = html;
        scheduleStreamRender(sectionStreamState, renderStreamingSection);
    }

    function stopStreamingSection() {
        if (sectionStreamState?.renderTimer) {
            window.clearTimeout(sectionStreamState.renderTimer);
        }

        sectionStreamState = null;
    }

    function saveQuickEditor() {
        const { save } = quickEditorElements();

        if (!window.builderQuickEdit.editId || !window.Livewire?.dispatch) {
            return;
        }

        save.disabled = true;
        save.textContent = 'Saving...';
        window.Livewire.dispatch('quick-edit-save', {
            editId: window.builderQuickEdit.editId,
            html: currentEditorValue(),
        });
    }

    function fallbackCopyText(value) {
        const ta = document.createElement('textarea');
        ta.value = value;
        ta.setAttribute('readonly', 'readonly');
        ta.style.position = 'fixed';
        ta.style.top = '-9999px';
        ta.style.left = '-9999px';
        document.body.appendChild(ta);
        ta.select();

        try {
            return document.execCommand('copy');
        } finally {
            ta.remove();
        }
    }

    async function copyQuickEditorHtml() {
        const { copy } = quickEditorElements();

        if (!copy || copy.disabled) {
            return;
        }

        copy.disabled = true;
        copy.textContent = 'Copying...';

        try {
            const value = currentEditorValue();

            if (navigator.clipboard?.writeText) {
                await navigator.clipboard.writeText(value);
            } else if (!fallbackCopyText(value)) {
                throw new Error('Clipboard copy failed.');
            }

            copy.textContent = 'Copied';
        } catch {
            copy.textContent = 'Copy failed';
        } finally {
            setTimeout(resetQuickEditorCopyButton, 1500);
        }
    }

    function bindQuickEditorControls() {
        const { close, cancel, copy, save, editorHost } = quickEditorElements();

        if (close && close.dataset.quickEditorBound !== 'true') {
            close.addEventListener('click', hideQuickEditor);
            close.dataset.quickEditorBound = 'true';
        }

        if (cancel && cancel.dataset.quickEditorBound !== 'true') {
            cancel.addEventListener('click', hideQuickEditor);
            cancel.dataset.quickEditorBound = 'true';
        }

        if (copy && copy.dataset.quickEditorBound !== 'true') {
            copy.addEventListener('click', copyQuickEditorHtml);
            copy.dataset.quickEditorBound = 'true';
        }

        if (save && save.dataset.quickEditorBound !== 'true') {
            save.addEventListener('click', saveQuickEditor);
            save.dataset.quickEditorBound = 'true';
        }

        if (editorHost && editorHost.dataset.quickEditorBound !== 'true') {
            editorHost.addEventListener('keydown', (event) => {
                if ((event.ctrlKey || event.metaKey) && event.key === 'Enter') {
                    event.preventDefault();
                    saveQuickEditor();
                }

                if (event.key === 'Escape') {
                    event.preventDefault();
                    hideQuickEditor();
                }
            });
            editorHost.dataset.quickEditorBound = 'true';
        }

        ensureCodeEditor();
    }

    function bindPreviewFrame() {
        const previewFrame = frame();

        if (!previewFrame || previewFrame.dataset.canvasBound === 'true') {
            return;
        }

        previewFrame.addEventListener('load', () => syncPreviewSelection(false));
        previewFrame.dataset.canvasBound = 'true';
        syncPreviewSelection(false);
    }

    function updatePreviewHtml(srcdoc, selectedNodeId = null) {
        const previewFrame = frame();

        if (!previewFrame || typeof srcdoc !== 'string' || srcdoc === '') {
            return;
        }

        if (typeof selectedNodeId === 'string') {
            previewFrame.dataset.selectedNodeId = selectedNodeId;
        }

        previewFrame.dataset.canvasBound = 'false';
        previewFrame.srcdoc = srcdoc;
        bindPreviewFrame();
    }

    function notifyPreviewRestored() {
        window.dispatchEvent(new CustomEvent('builder-preview-restored'));
    }

    function bootCanvas() {
        bindQuickEditorControls();
        bindPreviewFrame();
    }

    if (window.builderCanvasBound !== true) {
        window.builderCanvasBound = true;

        window.addEventListener('preview-selection-changed', (event) => {
            const previewFrame = frame();

            if (previewFrame) {
                previewFrame.dataset.selectedNodeId = event.detail?.nodeId || '';
            }

            syncPreviewSelection(event.detail?.scrollIntoView === true);
        });

        window.addEventListener('preview-html-updated', (event) => {
            stopStreamingSection();
            updatePreviewHtml(event.detail?.srcdoc, event.detail?.selectedNodeId || '');
        });

        window.addEventListener('section-generation-stream-start', () => {
            startStreamingSection();
        });

        window.addEventListener('section-generation-stream', (event) => {
            updateStreamingSection(event.detail?.html || '');
        });

        window.addEventListener('quick-edit-saved', (event) => {
            replaceQuickEditedElement(event.detail?.editId, event.detail?.html);
            resetQuickEditorSaveButton();
            hideQuickEditor();
        });

        window.addEventListener('targeted-edit-stream-start', (event) => {
            startStreamingTargetedBlocks(event.detail?.targetIds || []);
        });

        window.addEventListener('targeted-edit-stream', (event) => {
            updateStreamingTargetedBlocks(event.detail?.targetIds || [], event.detail?.html || '');
        });

        window.addEventListener('targeted-edit-stream-cancel', (event) => {
            cancelStreamingTargetedBlocks(event.detail?.targetIds || []);
        });

        window.addEventListener('targeted-edit-applied', (event) => {
            clearTargetedStreamTimer();
            targetedStreamState = null;
            replaceTargetedBlocks(event.detail?.targetIds || [], event.detail?.html || '');
        });

        window.addEventListener('quick-edit-failed', (event) => {
            const { error } = quickEditorElements();
            resetQuickEditorSaveButton();

            if (!error) {
                return;
            }

            error.textContent = (event.detail?.errors || ['The edited HTML could not be saved.']).join(' ');
            error.classList.remove('hidden');
        });

        window.addEventListener('message', (event) => {
            if (event.data?.type !== 'builder:node-selected') {
                return;
            }

            const previewFrame = frame();

            if (previewFrame) {
                previewFrame.dataset.selectedNodeId = event.data.nodeId || '';
            }

            notifyPreviewRestored();

            if (event.data.openQuickEdit === true) {
                showQuickEditor(event.data.quickEdit);
            } else {
                hideQuickEditor();
            }

            if (window.Livewire?.dispatch) {
                window.Livewire.dispatch('node-selected', {
                    nodeId: event.data.nodeId,
                    scrollIntoView: false,
                });
            }
        });

        document.addEventListener('livewire:init', () => {
            if (!window.Livewire?.hook) {
                return;
            }

            window.Livewire.hook('morphed', bootCanvas);
            window.Livewire.hook('morph.updated', bootCanvas);
        }, { once: true });

        document.addEventListener('livewire:navigated', bootCanvas);
        document.addEventListener('DOMContentLoaded', bootCanvas);
    }

    bootCanvas();
})();
