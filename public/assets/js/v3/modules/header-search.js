// A non-modal disclosure. The link remains a normal search-page link without JS.
export default function initHeaderSearch() {
  const toggle = document.querySelector('.hd-search-toggle');
  const panel = document.getElementById('header-search');
  if (!toggle || !panel) return;
  const input = panel.querySelector('input[name="q"]');
  const close = (restoreFocus = false) => {
    panel.hidden = true;
    toggle.setAttribute('aria-expanded', 'false');
    if (restoreFocus) toggle.focus();
  };
  toggle.setAttribute('role', 'button');
  toggle.setAttribute('aria-controls', panel.id);
  toggle.setAttribute('aria-expanded', 'false');
  toggle.addEventListener('click', event => {
    // Preserve open-in-new-tab and other native link shortcuts.
    if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
    event.preventDefault();
    if (!panel.hidden) return close(true);
    panel.hidden = false;
    toggle.setAttribute('aria-expanded', 'true');
    input.focus();
  });
  toggle.addEventListener('keydown', event => {
    if (event.key === ' ' || event.key === 'Spacebar') {
      event.preventDefault();
      toggle.click();
    }
  });
  panel.querySelector('.header-search__close').addEventListener('click', () => close(true));
  document.addEventListener('keydown', event => {
    if (!panel.hidden && event.key === 'Escape') {
      event.preventDefault();
      close(true);
    }
  });
  document.addEventListener('click', event => {
    if (!panel.hidden && !panel.contains(event.target) && !toggle.contains(event.target)) close();
  });
  document.addEventListener('focusin', event => {
    if (!panel.hidden && !panel.contains(event.target) && event.target !== toggle) close();
  });
  const menuToggle = document.querySelector('.hd-toggle');
  if (menuToggle) menuToggle.addEventListener('click', () => close());
  panel.querySelector('form').addEventListener('submit', event => {
    input.value = input.value.trim();
    if (!input.value) {
      event.preventDefault();
      input.focus();
      if (typeof input.reportValidity === 'function') input.reportValidity();
    }
  });
  window.addEventListener('pageshow', () => close());
}
