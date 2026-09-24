document.querySelectorAll('[data-audit-detail]').forEach(button => {
 button.addEventListener('click', () => {
  const detail = document.getElementById(button.getAttribute('aria-controls'));
  const open = button.getAttribute('aria-expanded') !== 'true';
  detail.hidden = !open;
  button.setAttribute('aria-expanded', String(open));
  button.textContent = open ? 'Thu gọn' : 'Chi tiết';
 });
});
