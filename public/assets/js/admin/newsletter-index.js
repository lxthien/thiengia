(() => {
    const root = document.querySelector('.newsletter-manager');
    if (!root) return;
    const dialog = root.querySelector('#newsletter-confirm');
    let pending = null, confirmed = null;
    root.addEventListener('submit', event => {
        const form = event.target;
        if (!form.matches('[data-newsletter-delete]')) return;
        if (confirmed === form) { confirmed = null; return; }
        event.preventDefault();
        const message = 'Xóa đăng ký của ' + form.dataset.email + '?';
        if (typeof dialog.showModal !== 'function') {
            if (window.confirm(message + ' Không thể hoàn tác.')) { confirmed = form; form.requestSubmit(); }
            return;
        }
        pending = form;
        root.querySelector('#newsletter-confirm-message').textContent = message;
        dialog.showModal(); root.querySelector('#newsletter-cancel').focus();
    });
    root.querySelector('#newsletter-cancel').addEventListener('click', () => dialog.close());
    dialog.addEventListener('close', () => { pending = null; });
    root.querySelector('#newsletter-confirm-delete').addEventListener('click', () => {
        if (!pending) return;
        const form = pending; confirmed = form; dialog.close(); form.requestSubmit();
    });
    root.querySelectorAll('[data-copy-email]').forEach(button => {
        let timer;
        button.addEventListener('click', async () => {
            clearTimeout(timer);
            const feedback = button.closest('.newsletter-tools').querySelector('.newsletter-feedback');
            button.disabled = true;
            try {
                await navigator.clipboard.writeText(button.dataset.copyEmail);
                button.textContent = 'Đã sao chép';
                button.classList.add('is-copied');
                feedback.textContent = 'Đã sao chép email.';
            } catch (error) {
                button.textContent = 'Thử sao chép lại';
                button.classList.remove('is-copied');
                feedback.textContent = 'Không thể truy cập clipboard. Bạn có thể chọn và sao chép email trực tiếp.';
            } finally {
                button.disabled = false;
                timer = setTimeout(() => { button.textContent = 'Sao chép email'; button.classList.remove('is-copied'); feedback.textContent = ''; }, 4000);
            }
        });
    });
})();
