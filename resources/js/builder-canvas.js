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

    function bindQuickEditorControls() {
        const { close, cancel, save, editorHost } = quickEditorElements();

        if (save?.dataset.quickEditorBound === 'true') {
            ensureCodeEditor();
            return;
        }

        close?.addEventListener('click', hideQuickEditor);
        cancel?.addEventListener('click', hideQuickEditor);
        save?.addEventListener('click', saveQuickEditor);
        editorHost?.addEventListener('keydown', (event) => {
            if ((event.ctrlKey || event.metaKey) && event.key === 'Enter') {
                event.preventDefault();
                saveQuickEditor();
            }

            if (event.key === 'Escape') {
                event.preventDefault();
                hideQuickEditor();
            }
        });

        if (save) {
            save.dataset.quickEditorBound = 'true';
        }
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
            updatePreviewHtml(event.detail?.srcdoc, event.detail?.selectedNodeId || '');
        });

        window.addEventListener('quick-edit-saved', (event) => {
            replaceQuickEditedElement(event.detail?.editId, event.detail?.html);
            resetQuickEditorSaveButton();
            hideQuickEditor();
        });

        window.addEventListener('targeted-edit-applied', (event) => {
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
