// Mục lục gấp/mở nhúng trong nội dung bài (.ka-table-of-contents, do CKEditor sinh),
// khác với mục lục sidebar tự sinh ở toc.js. Bấm vào #main-toc để gấp/mở.
export default function initContentToc() {
  const blocks = document.querySelectorAll(".post-content .ka-table-of-contents");
  if (!blocks.length) return;

  blocks.forEach((block) => {
    block.addEventListener("click", (event) => {
      const header = event.target.closest("#main-toc");
      if (!header || !block.contains(header)) return;
      block.classList.toggle("collapsed");
    });
  });
}
