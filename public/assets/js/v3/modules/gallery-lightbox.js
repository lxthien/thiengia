// Slider "Hình ảnh hoạt động" + popup phóng to ảnh (theo album — mỗi thẻ
// là 1 hoạt động, lightbox chỉ duyệt prev/next trong ảnh của album đó)
export default function initGallery() {
  const track = document.querySelector("[data-gallery-track]");
  if (!track) return;

  // --- Lightbox ---
  const lightbox = document.querySelector(".lightbox");
  if (!lightbox) return;

  const cards = [...track.querySelectorAll(".gallery-item")];
  const img = lightbox.querySelector("img");
  const caption = lightbox.querySelector(".lightbox-caption");
  let items = [];
  let current = 0;
  let opener = null, previousOverflow = '';

  const show = (i) => {
    if (!items.length) return;
    current = (i + items.length) % items.length;
    const item = items[current];
    img.src = item.src;
    img.alt = item.alt || "";
    caption.textContent = item.caption || "";
  };

  const open = (albumImages, startIndex) => {
    items = albumImages;
    if (!items.length) return;
    show(startIndex);
    opener = document.activeElement;
    previousOverflow = document.body.style.overflow;
    lightbox.querySelectorAll('.lightbox-prev, .lightbox-next').forEach(button => { button.hidden = items.length < 2; });
    lightbox.classList.add("is-open");
    lightbox.setAttribute("aria-hidden", "false");
    document.body.style.overflow = "hidden";
    requestAnimationFrame(() => {
      if (lightbox.classList.contains('is-open')) lightbox.querySelector(".lightbox-close")?.focus();
    });
  };

  const close = () => {
    lightbox.classList.remove("is-open");
    lightbox.setAttribute("aria-hidden", "true");
    document.body.style.overflow = previousOverflow;
    opener?.focus({preventScroll: true});
  };

  cards.forEach((card) => {
    card.addEventListener("click", () => {
      let albumImages = [];
      try {
        albumImages = JSON.parse(card.dataset.albumImages || "[]");
      } catch (e) {
        albumImages = [];
      }
      open(albumImages, 0);
    });
  });
  lightbox.querySelector(".lightbox-close")?.addEventListener("click", close);
  lightbox.querySelector(".lightbox-prev")?.addEventListener("click", () => show(current - 1));
  lightbox.querySelector(".lightbox-next")?.addEventListener("click", () => show(current + 1));
  lightbox.addEventListener("click", (e) => {
    if (e.target === lightbox) close();
  });
  document.addEventListener("keydown", (e) => {
    if (!lightbox.classList.contains("is-open")) return;
    if (e.key === 'Tab') {
      const buttons = [...lightbox.querySelectorAll('button')].filter(button => !button.hidden);
      const first = buttons[0], last = buttons[buttons.length - 1];
      if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
      else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    }
    if (e.key === "Escape") close();
    if (e.key === "ArrowLeft") show(current - 1);
    if (e.key === "ArrowRight") show(current + 1);
  });
}
