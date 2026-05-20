(function () {
    let selected = null;

    function clearSelection() {
        if (selected) {
            selected.classList.remove('builder-selected');
        }
    }

    document.addEventListener('click', function (event) {
        const target = event.target.closest('[data-node-id]');
        if (!target) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();

        clearSelection();
        selected = target;
        selected.classList.add('builder-selected');

        window.parent.postMessage({
            type: 'builder:node-selected',
            nodeId: target.dataset.nodeId,
            nodeType: target.dataset.nodeType || null
        }, '*');
    }, true);

    window.addEventListener('message', function (event) {
        if (event.data?.type !== 'replace-subtree') {
            return;
        }

        const target = document.querySelector('[data-node-id="' + event.data.nodeId + '"]');
        if (!target || typeof event.data.html !== 'string') {
            return;
        }

        target.outerHTML = event.data.html;
    });
})();
