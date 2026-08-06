// Slideshow ảnh hero — cross-fade + ken burns, tự chuyển 6s
export default function initHeroSlider() {
  const wrap = document.querySelector("[data-hero-slides]");
  if (!wrap) return;

  const imgs = [...wrap.querySelectorAll("img")];
  const dots = [...document.querySelectorAll("[data-hero-dot]")];
  if (imgs.length < 2) return;

  const reduced = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  let index = 0;
  let timer;

  const show = (i) => {
    index = (i + imgs.length) % imgs.length;
    imgs.forEach((img, j) => img.classList.toggle("is-active", j === index));
    dots.forEach((d, j) => {
      if (j === index) d.setAttribute("aria-current", "true");
      else d.removeAttribute("aria-current");
    });
  };

  const restart = () => {
    clearInterval(timer);
    if (!reduced) timer = setInterval(() => show(index + 1), 6000);
  };

  dots.forEach((d, j) =>
    d.addEventListener("click", () => {
      show(j);
      restart();
    }),
  );

  show(0);
  restart();
}
