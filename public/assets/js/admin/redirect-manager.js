(() => {
 const root = document.querySelector('#admin_redirect_index .redirect-manager');
 if (!root) return;
 const dialog = root.querySelector('#redirect-confirm');
 let pending = null, confirmed = null;
 root.addEventListener('submit', event => {
  const form = event.target;
  if (!form.matches('[data-redirect-delete]')) return;
  if (confirmed === form) { confirmed = null; return; }
  event.preventDefault();
  const message = 'Xóa quy tắc có URL nguồn: ' + form.dataset.source;
  if (typeof dialog.showModal !== 'function') {
   if (window.confirm(message + '? Không thể hoàn tác.')) { confirmed = form; form.requestSubmit(); }
   return;
  }
  pending = form; root.querySelector('#redirect-confirm-message').textContent = message;
  dialog.showModal(); root.querySelector('#redirect-cancel').focus();
 });
 root.querySelector('#redirect-cancel').addEventListener('click', () => dialog.close());
 dialog.addEventListener('close', () => { pending = null; });
 root.querySelector('#redirect-delete').addEventListener('click', () => {
  if (!pending) return;
  const form = pending; confirmed = form; dialog.close(); form.requestSubmit();
 });
})();
