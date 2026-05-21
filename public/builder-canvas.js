(function () {
    window.builderQuickEdit = window.builderQuickEdit || {
        editId: null,
        visible: false,
    };

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
            error: document.getElementById('builder-quick-editor-error'),
            close: document.getElementById('builder-quick-editor-close'),
            cancel: document.getElementById('builder-quick-editor-cancel'),
            save: document.getElementById('builder-quick-editor-save'),
        };
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
        const panelHeight = panel.offsetHeight || 320;
        const preferredLeft = frameRect.left - stageRect.left + rect.x + Math.min(rect.width, 24) + 12;
        const preferredTop = frameRect.top - stageRect.top + rect.y;
        const maxLeft = Math.max(24, stage.clientWidth - panelWidth - 24);
        const maxTop = Math.max(24, stage.clientHeight - panelHeight - 24);

        panel.style.left = `${Math.max(24, Math.min(preferredLeft, maxLeft))}px`;
        panel.style.top = `${Math.max(24, Math.min(preferredTop, maxTop))}px`;
    }

    function showQuickEditor(quickEdit) {
        const { panel, tag, target, textarea, error } = quickEditorElements();

        if (!panel || !textarea || !quickEdit?.editId || typeof quickEdit.outerHTML !== 'string') {
            return;
        }

        window.builderQuickEdit.editId = quickEdit.editId;
        window.builderQuickEdit.visible = true;
        tag.textContent = `<${quickEdit.tagName || 'element'}>`;
        target.textContent = quickEdit.blockId || '';
        textarea.value = quickEdit.outerHTML;
        error.textContent = '';
        error.classList.add('hidden');
        panel.classList.remove('hidden');
        positionQuickEditor(quickEdit);
        textarea.focus();
        textarea.setSelectionRange(0, 0);
    }

    function resetQuickEditorSaveButton() {
        const { save } = quickEditorElements();

        if (!save) {
            return;
        }

        save.disabled = false;
        save.textContent = 'Save';
    }

    function replaceQuickEditedElement(editId, html) {
        const previewFrame = frame();

        if (!previewFrame?.contentWindow || !editId || typeof html !== 'string') {
            return;
        }

        previewFrame.contentWindow.postMessage({
            type: 'replace-quick-edit',
            editId,
            html,
        }, '*');
    }

    function saveQuickEditor() {
        const { textarea, save } = quickEditorElements();

        if (!window.builderQuickEdit.editId || !textarea || !window.Livewire?.dispatch) {
            return;
        }

        save.disabled = true;
        save.textContent = 'Saving...';
        window.Livewire.dispatch('quick-edit-save', {
            editId: window.builderQuickEdit.editId,
            html: textarea.value,
        });
    }

    function bindQuickEditorControls() {
        const { close, cancel, save, textarea } = quickEditorElements();

        if (save?.dataset.quickEditorBound === 'true') {
            return;
        }

        close?.addEventListener('click', hideQuickEditor);
        cancel?.addEventListener('click', hideQuickEditor);
        save?.addEventListener('click', saveQuickEditor);
        textarea?.addEventListener('keydown', (event) => {
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

        window.addEventListener('quick-edit-saved', (event) => {
            replaceQuickEditedElement(event.detail?.editId, event.detail?.html);
            resetQuickEditorSaveButton();
            hideQuickEditor();
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

            syncPreviewSelection(false);
            showQuickEditor(event.data.quickEdit);

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
