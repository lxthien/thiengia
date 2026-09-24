(() => {
 const form = document.querySelector('#global-settings-form');
 if (!form) return;
 const snapshot = () => JSON.stringify(Array.from(new FormData(form).entries()));
 const initial = snapshot();
 let submitting = false;
 const bar = form.querySelector('.settings-save');
 const status = form.querySelector('#settings-save-status');
 const refresh = () => {
   const dirty = snapshot() !== initial;
   bar.classList.toggle('is-dirty', dirty);
   status.textContent = dirty ? 'Có thay đổi chưa lưu.' : 'Chưa có thay đổi mới.';
 };
 form.addEventListener('input', refresh);
 form.addEventListener('change', refresh);
 form.addEventListener('submit', event => {
   if (event.defaultPrevented) return;
   submitting = true;
   status.textContent = 'Đang gửi cấu hình…';
 });
 window.addEventListener('beforeunload', event => {
   if (!submitting && snapshot() !== initial) { event.preventDefault(); event.returnValue = ''; }
 });
 window.addEventListener('pageshow', () => { submitting = false; refresh(); });
 const errors = document.querySelector('#settings-errors');
 if (errors) errors.focus();
})();
