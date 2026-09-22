(() => {
    const root = document.querySelector('#admin_comment_index .comment-manager');
    if (!root) return;
    const bulk = root.querySelector('#comment-bulk');
    const checks = Array.from(root.querySelectorAll('[data-comment-check]'));
    const all = root.querySelector('#comment-select-all');
    const action = bulk && bulk.elements.bulk_action;
    const dialog = root.querySelector('#comment-confirm');
    let pending = null;
    let confirmed = null;
    const refresh = () => {
        if (!bulk) return;
        const count = checks.filter(input => input.checked).length;
        all.checked = count > 0 && count === checks.length;
        all.indeterminate = count > 0 && count < checks.length;
        root.querySelector('#comment-selection').textContent = 'Đã chọn ' + count + ' bình luận';
        root.querySelector('#comment-apply').disabled = !count || !action.value;
        checks.forEach(input => input.closest('.comment-row').classList.toggle('is-selected', input.checked));
    };
    if (bulk) {
        all.addEventListener('change', () => { checks.forEach(input => { input.checked = all.checked; }); refresh(); });
        checks.forEach(input => input.addEventListener('change', refresh));
        action.addEventListener('change', refresh);
    }
    root.addEventListener('submit', event => {
        const form = event.target;
        if (form === bulk && (!checks.some(input => input.checked) || !action.value)) {
            event.preventDefault(); refresh(); return;
        }
        const deleting = form.matches('[data-comment-delete]') || (form === bulk && action.value === 'delete');
        if (!deleting || confirmed === form) { confirmed = null; return; }
        event.preventDefault();
        const message = form === bulk
            ? 'Bạn đang xóa ' + checks.filter(input => input.checked).length + ' bình luận đã chọn.'
            : 'Bạn đang xóa bình luận của ' + form.dataset.commentAuthor + '.';
        if (typeof dialog.showModal !== 'function') {
            if (window.confirm(message + '\nCác trả lời đi kèm cũng bị xóa. Không thể hoàn tác.')) {
                confirmed = form; form.requestSubmit();
            }
            return;
        }
        pending = form;
        root.querySelector('#comment-confirm-message').textContent = message;
        dialog.showModal();
        root.querySelector('#comment-cancel').focus();
    });
    root.querySelector('#comment-cancel').addEventListener('click', () => dialog.close());
    dialog.addEventListener('close', () => { pending = null; });
    root.querySelector('#comment-confirm-delete').addEventListener('click', () => {
        if (!pending) return;
        const form = pending;
        confirmed = form;
        dialog.close();
        form.requestSubmit();
    });
    window.addEventListener('pageshow', refresh);
    refresh();
})();
