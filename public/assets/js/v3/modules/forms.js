import { showToast } from "./toast";

// Phản hồi khi gửi form. Mọi form[data-quote] khoá nút khi submit để tránh
// double-submit. Form nào có thêm data-ajax-form thì gửi bằng fetch() tới
// action của form (route trả JSON {success, message}) và hiện toast, thay
// vì để trình duyệt tải lại trang.
export default function initForms() {
  document.querySelectorAll("form[data-quote]").forEach((form) => {
    const isAjax = form.hasAttribute("data-ajax-form");

    // Mặc định trình duyệt validate ngay khi submit rồi bám live theo từng
    // ký tự gõ tiếp (bubble lỗi tự cập nhật liên tục) — gây cảm giác báo lỗi
    // ngay cả khi chưa gõ xong. Tắt validate tự động (novalidate), tự kiểm
    // tra bằng JS và chỉ báo lỗi khi rời khỏi ô (blur) hoặc khi bấm gửi.
    form.setAttribute("novalidate", "");
    form.querySelectorAll("input, textarea, select").forEach((field) => {
      field.addEventListener("blur", () => {
        if (!field.validity.valid) field.reportValidity();
      });
    });

    form.addEventListener("submit", (event) => {
      if (!form.checkValidity()) {
        event.preventDefault();
        form.reportValidity();
        return;
      }

      const btn = form.querySelector("button[type=submit]");
      const lockButton = () => {
        if (!btn) return;
        btn.disabled = true;
        btn.dataset.originalText = btn.dataset.originalText || btn.textContent;
        btn.textContent = "Đang gửi…";
      };
      const unlockButton = () => {
        if (!btn) return;
        btn.disabled = false;
        btn.textContent = btn.dataset.originalText || btn.textContent;
      };

      lockButton();

      if (!isAjax) return;

      event.preventDefault();

      fetch(form.action, {
        method: "POST",
        body: new FormData(form),
        headers: { "X-Requested-With": "XMLHttpRequest" },
      })
        .then((response) => response.json())
        .then((data) => {
          showToast(data.success ? "success" : "error", data.message || "Đã có lỗi xảy ra. Vui lòng thử lại.");
          if (data.success) form.reset();
        })
        .catch(() => {
          showToast("error", "Không thể gửi yêu cầu. Vui lòng thử lại hoặc gọi hotline.");
        })
        .finally(unlockButton);
    });
  });
}
