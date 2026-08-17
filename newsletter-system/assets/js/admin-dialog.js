(function () {
    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = value == null ? '' : String(value);
        return div.innerHTML;
    }

    function showConfirm(options) {
        return new Promise(resolve => {
            const overlay = document.createElement('div');
            overlay.className = 'app-dialog-backdrop';
            overlay.innerHTML = `<div class="app-dialog" role="dialog" aria-modal="true" aria-labelledby="adminDialogTitle">
                <button class="app-dialog-close" type="button" aria-label="Close">${closeIcon()}</button>
                <div class="app-dialog-icon danger">${trashIcon()}</div>
                <h2 id="adminDialogTitle">${escapeHtml(options.title || 'Confirm action')}</h2>
                <p>${escapeHtml(options.message || '')}</p>
                <div class="app-dialog-actions">
                    <button class="btn btn-outline-secondary" type="button" data-dialog-cancel>Cancel</button>
                    <button class="btn btn-danger" type="button" data-dialog-confirm>${escapeHtml(options.confirmText || 'Delete')}</button>
                </div>
            </div>`;
            document.body.appendChild(overlay);

            const confirmButton = overlay.querySelector('[data-dialog-confirm]');
            const cancelButton = overlay.querySelector('[data-dialog-cancel]');
            const closeButton = overlay.querySelector('.app-dialog-close');
            const previousFocus = document.activeElement;

            function close(value) {
                overlay.remove();
                document.removeEventListener('keydown', onKeydown);
                if (previousFocus && typeof previousFocus.focus === 'function') previousFocus.focus();
                resolve(value);
            }

            function onKeydown(event) {
                if (event.key === 'Escape') close(false);
                if (event.key === 'Enter' && event.target === confirmButton) {
                    event.preventDefault();
                    close(true);
                }
            }

            confirmButton.addEventListener('click', () => close(true));
            cancelButton.addEventListener('click', () => close(false));
            closeButton.addEventListener('click', () => close(false));
            overlay.addEventListener('click', event => {
                if (event.target === overlay) close(false);
            });
            document.addEventListener('keydown', onKeydown);
            setTimeout(() => confirmButton.focus(), 0);
        });
    }

    document.addEventListener('click', async event => {
        const button = event.target.closest('[data-confirm]');
        if (!button || button.dataset.confirmed === '1') return;
        event.preventDefault();

        const accepted = await showConfirm({
            title: button.dataset.confirm,
            message: button.dataset.confirmDetail || '',
            confirmText: button.dataset.confirmAction || 'Confirm'
        });
        if (!accepted) return;

        button.dataset.confirmed = '1';
        if (button.form && typeof button.form.requestSubmit === 'function') {
            button.form.requestSubmit(button);
        } else if (button.form) {
            button.form.submit();
        }
    });

    function closeIcon() {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>';
    }

    function trashIcon() {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M6 6l1 15h10l1-15"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>';
    }
})();
