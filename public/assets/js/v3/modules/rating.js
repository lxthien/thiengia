import { showToast } from "./toast";

// Widget đánh giá sao trên trang bài viết — bấm 1 sao là gửi luôn, không cần
// nút Gửi riêng. Endpoint /rating trả {success, message, rating, ratingCount}
// (xem NewsController::ratingAction), dùng để cập nhật số liệu tại chỗ.
export default function initRating() {
  const widget = document.querySelector("[data-rating-widget]");
  if (!widget) return;

  const form = widget.querySelector("[data-rating-form]");
  const stars = Array.from(widget.querySelectorAll("[data-rating-star]"));
  const summary = widget.querySelector("[data-rating-summary]");
  const label = widget.querySelector(".post-rating-label");
  const input = widget.querySelector("[data-rating-value]");

  if (!form || !stars.length || !input) return;

  const paintStars = (value) => {
    stars.forEach((star) => {
      star.classList.toggle("is-filled", Number(star.dataset.ratingStar) <= value);
    });
  };

  stars.forEach((star) => {
    const value = Number(star.dataset.ratingStar);

    star.addEventListener("mouseenter", () => {
      if (!widget.classList.contains("is-voted")) paintStars(value);
    });

    star.addEventListener("mouseleave", () => {
      if (!widget.classList.contains("is-voted")) {
        paintStars(Number(widget.dataset.currentAverage || 0));
      }
    });

    star.addEventListener("click", () => {
      if (widget.classList.contains("is-voted") || widget.classList.contains("is-loading")) return;

      input.value = value;
      widget.classList.add("is-loading");
      stars.forEach((s) => (s.disabled = true));

      fetch(form.action, {
        method: "POST",
        body: new FormData(form),
        headers: { "X-Requested-With": "XMLHttpRequest" },
      })
        .then((response) => response.json())
        .then((data) => {
          showToast(data.success ? "success" : "error", data.message || "Đã có lỗi xảy ra. Vui lòng thử lại.");

          if (data.success) {
            widget.classList.add("is-voted");
            paintStars(value);
            if (label) label.textContent = "Cảm ơn bạn đã đánh giá bài viết này";
            if (summary && typeof data.rating !== "undefined") {
              summary.textContent = `${data.rating}/5 (${data.ratingCount} lượt đánh giá)`;
            }
          } else {
            stars.forEach((s) => (s.disabled = false));
            paintStars(Number(widget.dataset.currentAverage || 0));
          }
        })
        .catch(() => {
          showToast("error", "Không thể gửi đánh giá. Vui lòng thử lại.");
          stars.forEach((s) => (s.disabled = false));
          paintStars(Number(widget.dataset.currentAverage || 0));
        })
        .finally(() => widget.classList.remove("is-loading"));
    });
  });
}
