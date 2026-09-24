(() => {
 const root = document.querySelector('#admin_user_index .user-manager');
 if (!root) return;
 const dialog = root.querySelector('#user-confirm');
 let pending = null, confirmed = null;
 root.addEventListener('submit', event => {
  const form = event.target;
  if (!form.matches('[data-user-confirm]')) return;
  if (confirmed === form) { confirmed = null; return; }
  event.preventDefault();
  const message = form.dataset.userConfirm;
  if (typeof dialog.showModal !== 'function') { if (window.confirm(message)) { confirmed = form; form.requestSubmit(); } return; }
  pending = form; root.querySelector('#user-confirm-message').textContent = message;
  dialog.showModal(); root.querySelector('#user-cancel').focus();
 });
 root.querySelector('#user-cancel').addEventListener('click', () => dialog.close());
 dialog.addEventListener('close', () => { pending = null; });
 root.querySelector('#user-proceed').addEventListener('click', () => { if (!pending) return; const form = pending; confirmed = form; dialog.close(); form.requestSubmit(); });
})();
