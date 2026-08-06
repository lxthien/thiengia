// Mục lục bài viết: tự sinh từ h2 trong .post-content + scrollspy.
// Nội dung bài đến từ CKEditor nên id được gán ở client nếu thiếu.
export default function initToc() {
  const toc = document.querySelector("[data-toc]");
  const content = document.querySelector(".post-content");
  if (!toc || !content) return;

  const slugify = (text, i) =>
    text
      .toLowerCase()
      .normalize("NFD")
      // bỏ dấu tiếng Việt (dải combining diacritics)
      .replace(/[̀-ͯ]/g, "")
      .replace(/đ/g, "d") // đ → d
      .replace(/[^a-z0-9]+/g, "-")
      .replace(/^-+|-+$/g, "") || `muc-${i + 1}`;

  const headings = [...content.querySelectorAll("h2")];
  if (!headings.length) {
    toc.hidden = true;
    return;
  }

  const list = toc.querySelector("ol");
  headings.forEach((h, i) => {
    if (!h.id) h.id = slugify(h.textContent, i);
    const li = document.createElement("li");
    const a = document.createElement("a");
    a.href = `#${h.id}`;
    a.textContent = h.textContent;
    li.appendChild(a);
    list.appendChild(li);
  });

  const links = [...list.querySelectorAll("a")];
  const byId = new Map(links.map((l) => [l.getAttribute("href").slice(1), l]));

  const spy = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          links.forEach((l) => l.removeAttribute("aria-current"));
          byId.get(entry.target.id)?.setAttribute("aria-current", "true");
        }
      });
    },
    { rootMargin: "-15% 0px -75% 0px" },
  );

  headings.forEach((h) => spy.observe(h));
}
