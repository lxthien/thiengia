// Carousel đánh giá khách hàng — tự chạy 6.5s, dừng khi hover/focus
export default function initQuotes() {
  const root = document.querySelector("[data-quotes]");
  if (!root) return;

  const track = root.querySelector(".quotes-track");
  const slides = track ? [...track.children] : [];
  const dots = [...document.querySelectorAll("[data-quotes-dot]")];
  if (slides.length < 2) return;

  const reduced = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  let index = 0;
  let timer;

  const goTo = (i) => {
    index = (i + slides.length) % slides.length;
    track.style.transform = `translateX(-${index * 100}%)`;
    slides.forEach((s, j) => s.setAttribute("aria-hidden", j === index ? "false" : "true"));
    dots.forEach((d, j) => {
      if (j === index) d.setAttribute("aria-current", "true");
      else d.removeAttribute("aria-current");
    });
  };

  const restart = () => {
    clearInterval(timer);
    if (!reduced) timer = setInterval(() => goTo(index + 1), 6500);
  };

  document.querySelector("[data-quotes-prev]")?.addEventListener("click", () => {
    goTo(index - 1);
    restart();
  });
  document.querySelector("[data-quotes-next]")?.addEventListener("click", () => {
    goTo(index + 1);
    restart();
  });
  dots.forEach((d, j) =>
    d.addEventListener("click", () => {
      goTo(j);
      restart();
    }),
  );

  root.addEventListener("mouseenter", () => clearInterval(timer));
  root.addEventListener("mouseleave", restart);
  root.addEventListener("focusin", () => clearInterval(timer));
  root.addEventListener("focusout", restart);

  goTo(0);
  restart();
}
