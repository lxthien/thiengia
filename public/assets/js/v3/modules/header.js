// Đổ bóng header khi cuộn
export default function initHeader() {
  const hd = document.querySelector(".hd");
  if (!hd) return;

  const onScroll = () => hd.classList.toggle("is-scrolled", window.scrollY > 24);
  window.addEventListener("scroll", onScroll, { passive: true });
  onScroll();
}
