// Form shortcode [form_tai_checklist] trong bài viết
// (templates/v3/components/_checklist_form.html.twig). Gửi AJAX do forms.js lo
// (data-quote + data-ajax-form); module này chỉ kiểm tra số điện thoại và khi
// thành công thì thay form bằng lời nhắn hoặc các nút tải file server trả về.
export default function initChecklistForms() {
  document.querySelectorAll("[data-checklist]").forEach((box) => {
    const form = box.querySelector("form");
    const phone = form.querySelector("[data-checklist-phone]");
    const done = box.querySelector("[data-checklist-done]");

    phone.addEventListener("input", () => {
      const value = phone.value.replace(/[\s.\-()]/g, "");
      // Giữ đồng bộ với CostLeadType::PHONE_PATTERN
      phone.setCustomValidity(
        value && !/^(0|\+84)[35789]\d{8}$/.test(value) ? "Số điện thoại không hợp lệ, ví dụ 0901 234 567." : ""
      );
    });

    form.addEventListener("ajax-form:success", (event) => {
      const { message = "", files = [] } = event.detail || {};
      const list = done.querySelector("[data-checklist-files]");

      done.querySelector("[data-checklist-message]").textContent = message;
      list.replaceChildren(
        ...files.map((file) => {
          const link = document.createElement("a");
          link.className = "btn2 checklist-file";
          link.href = file.url;
          link.textContent = file.label;
          if (/^https?:\/\//i.test(file.url) && new URL(file.url).host !== location.host) {
            link.target = "_blank";
            link.rel = "noopener";
          } else {
            link.setAttribute("download", "");
          }
          return link;
        })
      );
      list.hidden = files.length === 0;

      form.hidden = true;
      done.hidden = false;
      done.focus();
    });
  });
}
