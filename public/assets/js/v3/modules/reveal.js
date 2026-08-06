// Hiệu ứng hiện dần khi cuộn tới
export default function initReveal() {
  const items = document.querySelectorAll(".fade");
  if (!items.length) return;

  const io = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          entry.target.classList.add("is-in");
          io.unobserve(entry.target);
        }
      });
    },
    { threshold: 0.15 },
  );

  items.forEach((el) => io.observe(el));
}
