// Content-aware layout: measure real labels after fonts load, not only viewport breakpoints.
export default function initAdaptiveHeader() {
  const header = document.querySelector(".hd");
  const row = header?.querySelector(".hd-inner");
  const nav = header?.querySelector(".hd-nav");
  const actions = header?.querySelector(".hd-actions");
  const logo = header?.querySelector(".hd-logo");
  if (!row || !nav || !actions || !logo) return;
  let frame;
  const fit = () => {
    frame = null;
    const previous = header.dataset.layout;
    header.classList.remove("hd-hide-cta", "hd-hide-phone");
    header.dataset.layout = "full";
    const style = getComputedStyle(row);
    const available = row.clientWidth - parseFloat(style.paddingLeft) - parseFloat(style.paddingRight);
    const gap = parseFloat(style.columnGap) || 0;
    const width = element => element.getBoundingClientRect().width;
    const fits = () => width(logo) + width(nav) + width(actions) + gap * 2 <= available - 2;
    // Touch users get the same explicit menu controls as mobile.
    let mode = "drawer";
    if (matchMedia("(min-width: 1024px) and (hover: hover)").matches) {
      if (fits()) mode = "full";
      else {
        header.dataset.layout = "compact";
        if (fits()) mode = "compact";
      }
    }
    header.dataset.layout = mode;
    if (mode === "drawer") {
      const fitsDrawer = () => width(logo) + width(actions) + gap <= available - 2;
      if (!fitsDrawer()) header.classList.add("hd-hide-cta");
      if (!fitsDrawer()) header.classList.add("hd-hide-phone");
    }
    if (mode !== "drawer" && previous === "drawer") {
      header.dispatchEvent(new Event("header:desktop"));
    }
    // Align dropdown panels within the viewport, including right-most nav items.
    if (mode !== "drawer") {
      nav.querySelectorAll(".hd-drop-menu").forEach(panel => {
        panel.style.setProperty("--dropdown-shift", "0px");
        const rect = panel.getBoundingClientRect();
        const edge = document.documentElement.clientWidth - 12;
        const shift = rect.left < 12 ? 12 - rect.left : rect.right > edge ? edge - rect.right : 0;
        panel.style.setProperty("--dropdown-shift", shift + "px");
      });
    }
  };
  const schedule = () => { if (!frame) frame = requestAnimationFrame(fit); };
  new ResizeObserver(schedule).observe(row);
  new MutationObserver(schedule).observe(nav, { childList: true, subtree: true, characterData: true });
  window.addEventListener("resize", schedule, { passive: true });
  document.fonts?.ready.then(schedule);
  document.fonts?.addEventListener("loadingdone", schedule);
  fit();
}
