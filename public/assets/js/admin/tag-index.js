// Danh sách thẻ: hộp xác nhận xóa (cùng cách làm với newscategory-index.js).
(() => {
    const root = document.querySelector('#admin_tag_index .tag-manager');
    if (!root) return;

    const dialog = root.querySelector('#tag-confirm');
    let pending = null;
    let confirmed = null;

    root.addEventListener('submit', (e) => {
        const form = e.target;
        if (!form.matches('[data-tag-delete]')) return;
        if (confirmed === form) { confirmed = null; return; }
        e.preventDefault();

        const count = parseInt(form.dataset.news, 10) || 0;
        const message = 'Xóa thẻ “' + form.dataset.title + '”?';
        const warning = (count ? 'Thẻ sẽ bị gỡ khỏi ' + count + ' bài viết (bài viết không bị xóa). ' : '')
            + 'Không thể hoàn tác; trang /tag/… của thẻ sẽ trả về lỗi 404 nếu chưa tạo chuyển hướng.';

        if (typeof dialog.showModal !== 'function') {
            if (window.confirm(message + '\n' + warning)) { confirmed = form; form.requestSubmit(); }
            return;
        }

        pending = form;
        root.querySelector('#tag-confirm-message').textContent = message;
        root.querySelector('#tag-confirm-warning').textContent = warning;
        dialog.showModal();
        root.querySelector('#tag-cancel').focus();
    });

    root.querySelector('#tag-cancel').addEventListener('click', () => dialog.close());
    dialog.addEventListener('close', () => { pending = null; });
    root.querySelector('#tag-delete').addEventListener('click', () => {
        if (!pending) return;
        const form = pending;
        confirmed = form;
        dialog.close();
        form.requestSubmit();
    });
})();
