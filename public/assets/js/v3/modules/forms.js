// Phản hồi khi gửi form.
// Ở bước này form vẫn submit thật về Symfony (không chặn) — chỉ khoá nút
// để tránh double-submit. Khi làm bước "form" sẽ thay bằng AJAX nếu cần.
export default function initForms() {
  document.querySelectorAll("form[data-quote]").forEach((form) => {
    form.addEventListener("submit", () => {
      const btn = form.querySelector("button[type=submit]");
      if (!btn) return;
      btn.disabled = true;
      btn.dataset.originalText = btn.textContent;
      btn.textContent = "Đang gửi…";
    });
  });
}
