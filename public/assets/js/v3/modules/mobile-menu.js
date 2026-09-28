// Full-screen navigation shared by narrow desktop, tablet and mobile.
export default function initMobileMenu() {
  const menu = document.querySelector(".hd-menu");
  const toggle = document.querySelector(".hd-toggle");
  if (!menu || !toggle) return;
  let previousFocus;
  let previousOverflow = "";
  const background = new Map();
  menu.inert = true;
  const close = (restoreFocus = true) => {
    if (!menu.classList.contains("is-open")) return;
    menu.classList.remove("is-open");
    menu.setAttribute("aria-hidden", "true");
    menu.inert = true;
    toggle.setAttribute("aria-expanded", "false");
    document.body.style.overflow = previousOverflow;
    background.forEach((value, element) => { element.inert = value; });
    background.clear();
    if (restoreFocus && previousFocus?.getClientRects().length) previousFocus.focus();
  };
  toggle.addEventListener("click", () => {
    if (menu.classList.contains("is-open")) return close();
    previousFocus = document.activeElement;
    previousOverflow = document.body.style.overflow;
    // Inert siblings at every level, without making the dialog itself inert.
    let branch = menu;
    while (branch.parentElement && branch !== document.body) {
      [...branch.parentElement.children].forEach(element => {
        if (element !== branch && element instanceof HTMLElement) {
          background.set(element, element.inert);
          element.inert = true;
        }
      });
      branch = branch.parentElement;
    }
    menu.inert = false;
    menu.classList.add("is-open");
    menu.setAttribute("aria-hidden", "false");
    toggle.setAttribute("aria-expanded", "true");
    document.body.style.overflow = "hidden";
    menu.scrollTop = 0;
    requestAnimationFrame(() => {
      if (menu.classList.contains("is-open")) menu.querySelector(".hd-menu-close")?.focus();
    });
  });
  menu.querySelector(".hd-menu-close")?.addEventListener("click", () => close());
  menu.querySelectorAll("a").forEach(a => a.addEventListener("click", () => close()));
  document.querySelector(".hd")?.addEventListener("header:desktop", () => {
    if (!menu.classList.contains("is-open")) return;
    close(false);
    document.querySelector(".hd-nav a")?.focus();
  });
  document.addEventListener("keydown", event => {
    if (!menu.classList.contains("is-open")) return;
    if (event.key === "Escape") { event.preventDefault(); close(); }
    if (event.key === "Tab") {
      const focusable = [...menu.querySelectorAll("a[href], button:not([disabled])")].filter(e => e.getClientRects().length);
      const first = focusable[0], last = focusable[focusable.length - 1];
      if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); }
      else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
    }
  });
}
