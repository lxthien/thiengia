(() => {
 const root = document.querySelector('.page-manager'); if (!root) return;
 root.querySelectorAll('[data-page-thumbnail]').forEach(img => {
     const fallback = () => { img.hidden = true; img.nextElementSibling.hidden = false; };
     img.addEventListener('error', fallback);
     if (img.complete && !img.naturalWidth) fallback();
 });
 const form = root.querySelector('#admin-page-bulk-form'), all = root.querySelector('#page-check-all'), checks = Array.from(root.querySelectorAll('[data-page-check]'));
 const refresh = () => { const n=checks.filter(c=>c.checked).length; all.checked=n>0&&n===checks.length; all.indeterminate=n>0&&n<checks.length; root.querySelector('#page-selection').textContent='Đã chọn '+n+' trang'; root.querySelector('#page-bulk-apply').disabled=!n||!form.elements.bulk_action.value; checks.forEach(c=>c.closest('tr').classList.toggle('is-selected',c.checked)); };
 all.addEventListener('change',()=>{checks.forEach(c=>{c.checked=all.checked;});refresh();}); checks.forEach(c=>c.addEventListener('change',refresh)); form.elements.bulk_action.addEventListener('change',refresh);
 form.addEventListener('submit',e=>{if(!checks.some(c=>c.checked)||!form.elements.bulk_action.value)e.preventDefault();});
 const dialog=root.querySelector('#page-confirm'); let pending=null,confirmed=null;
 root.addEventListener('submit',e=>{const f=e.target;if(!f.matches('[data-page-delete]'))return;if(confirmed===f){confirmed=null;return;}e.preventDefault();const message='Xóa trang “'+f.dataset.title+'”?';if(typeof dialog.showModal!=='function'){if(window.confirm(message)){confirmed=f;f.requestSubmit();}return;}pending=f;root.querySelector('#page-confirm-message').textContent=message;dialog.showModal();root.querySelector('#page-cancel').focus();});
 root.querySelector('#page-cancel').addEventListener('click',()=>dialog.close());dialog.addEventListener('close',()=>{pending=null;});root.querySelector('#page-delete').addEventListener('click',()=>{if(!pending)return;const f=pending;confirmed=f;dialog.close();f.requestSubmit();});
 window.addEventListener('pageshow',refresh);refresh();
})();
