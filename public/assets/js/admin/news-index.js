(() => {
 const root = document.querySelector('#admin_news_index .news-manager'); if (!root) return;
 root.querySelectorAll('[data-news-thumbnail]').forEach(img => {
     const fallback = () => { img.hidden = true; img.nextElementSibling.hidden = false; };
     img.addEventListener('error', fallback);
     if (img.complete && !img.naturalWidth) fallback();
 });
 const form = root.querySelector('#admin-news-bulk-form'), all = root.querySelector('#news-check-all'), checks = Array.from(root.querySelectorAll('[data-news-check]'));
 const refresh = () => {
     const n = checks.filter(c => c.checked).length;
     all.checked = n > 0 && n === checks.length; all.indeterminate = n > 0 && n < checks.length;
     checks.forEach(c => c.closest('tr').classList.toggle('is-selected', c.checked));
     if (!form) return;
     root.querySelector('#news-selection').textContent = 'Đã chọn ' + n + ' bài viết';
     root.querySelector('#news-bulk-apply').disabled = !n || !form.elements.bulk_action.value;
 };
 all.disabled = !checks.length;
 all.addEventListener('change', () => { checks.forEach(c => { c.checked = all.checked; }); refresh(); });
 checks.forEach(c => c.addEventListener('change', refresh));
 if (form) {
     form.elements.bulk_action.addEventListener('change', refresh);
     form.addEventListener('submit', e => { if (!checks.some(c => c.checked) || !form.elements.bulk_action.value) e.preventDefault(); });
 }
 const dialog = root.querySelector('#news-confirm'); let pending = null, confirmed = null;
 root.addEventListener('submit', e => {
     const f = e.target; if (!f.matches('[data-news-delete]')) return;
     if (confirmed === f) { confirmed = null; return; }
     e.preventDefault();
     const message = 'Xóa bài viết “' + f.dataset.title + '”?';
     if (typeof dialog.showModal !== 'function') { if (window.confirm(message + '\n' + root.querySelector('#news-confirm-warning').textContent)) { confirmed = f; f.requestSubmit(); } return; }
     pending = f; root.querySelector('#news-confirm-message').textContent = message;
     dialog.showModal(); root.querySelector('#news-cancel').focus();
 });
 root.querySelector('#news-cancel').addEventListener('click', () => dialog.close());
 dialog.addEventListener('close', () => { pending = null; });
 root.querySelector('#news-delete').addEventListener('click', () => { if (!pending) return; const f = pending; confirmed = f; dialog.close(); f.requestSubmit(); });
 window.addEventListener('pageshow', refresh); refresh();
})();
