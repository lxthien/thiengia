// Đếm số liệu khi cuộn tới (data-count)
export default function initCounters() {
  const items = document.querySelectorAll("[data-count]");
  if (!items.length) return;

  const animate = (el) => {
    const target = parseInt(el.dataset.count, 10);
    if (Number.isNaN(target)) return;

    const start = performance.now();
    const tick = (now) => {
      const p = Math.min((now - start) / 1600, 1);
      el.textContent = Math.round(target * (1 - Math.pow(1 - p, 3)));
      if (p < 1) requestAnimationFrame(tick);
    };
    requestAnimationFrame(tick);
  };

  const io = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          animate(entry.target);
          io.unobserve(entry.target);
        }
      });
    },
    { threshold: 0.5 },
  );

  items.forEach((el) => io.observe(el));
}
