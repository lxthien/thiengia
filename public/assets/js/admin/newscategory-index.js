(() => {
 const root = document.querySelector('#admin_category_index .category-manager'); if (!root) return;
 root.querySelectorAll('[data-category-thumbnail]').forEach(img => {
     const fallback = () => { img.hidden = true; img.nextElementSibling.hidden = false; };
     img.addEventListener('error', fallback);
     if (img.complete && !img.naturalWidth) fallback();
 });
 const dialog = root.querySelector('#category-confirm'); let pending = null, confirmed = null;
 root.addEventListener('submit', e => {
     const f = e.target; if (!f.matches('[data-category-delete]')) return;
     if (confirmed === f) { confirmed = null; return; }
     e.preventDefault();
     const count = parseInt(f.dataset.news, 10) || 0;
     const message = 'Xóa danh mục “' + f.dataset.title + '”?';
     const warning = (count ? count + ' bài viết sẽ bị gỡ khỏi danh mục này (bài viết không bị xóa). ' : '') + 'Thao tác không thể hoàn tác; URL danh mục sẽ trả về lỗi 404 nếu chưa tạo chuyển hướng.';
     if (typeof dialog.showModal !== 'function') { if (window.confirm(message + '\n' + warning)) { confirmed = f; f.requestSubmit(); } return; }
     pending = f;
     root.querySelector('#category-confirm-message').textContent = message;
     root.querySelector('#category-confirm-warning').textContent = warning;
     dialog.showModal(); root.querySelector('#category-cancel').focus();
 });
 root.querySelector('#category-cancel').addEventListener('click', () => dialog.close());
 dialog.addEventListener('close', () => { pending = null; });
 root.querySelector('#category-delete').addEventListener('click', () => { if (!pending) return; const f = pending; confirmed = f; dialog.close(); f.requestSubmit(); });
})();
