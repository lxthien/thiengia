(() => {
    const root = document.querySelector('#admin_contact_index .contact-manager');
    if (!root) return;
    const dialog = root.querySelector('#contact-confirm');
    let pending = null;
    let confirmed = null;
    root.addEventListener('submit', event => {
        const form = event.target;
        if (!form.matches('[data-contact-delete]')) return;
        if (confirmed === form) { confirmed = null; return; }
        event.preventDefault();
        const message = 'Xóa liên hệ của ' + form.dataset.contactName + '?';
        if (typeof dialog.showModal !== 'function') {
            if (window.confirm(message + ' Không thể hoàn tác.')) { confirmed = form; form.requestSubmit(); }
            return;
        }
        pending = form;
        root.querySelector('#contact-confirm-message').textContent = message;
        dialog.showModal();
        root.querySelector('#contact-cancel').focus();
    });
    root.querySelector('#contact-cancel').addEventListener('click', () => dialog.close());
    dialog.addEventListener('close', () => { pending = null; });
    root.querySelector('#contact-confirm-delete').addEventListener('click', () => {
        if (!pending) return;
        const form = pending;
        confirmed = form;
        dialog.close();
        form.requestSubmit();
    });
})();
