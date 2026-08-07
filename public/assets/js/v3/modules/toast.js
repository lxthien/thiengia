// Thông báo nổi (thành công/lỗi) — dùng bởi forms.js sau khi submit AJAX.
let stack;

function ensureStack() {
  if (stack) return stack;
  stack = document.createElement("div");
  stack.className = "v3-toast-stack";
  stack.setAttribute("aria-live", "polite");
  stack.setAttribute("aria-atomic", "true");
  document.body.appendChild(stack);
  return stack;
}

export function showToast(type, message) {
  const el = document.createElement("div");
  el.className = `v3-toast v3-toast--${type === "error" ? "error" : "success"}`;
  el.setAttribute("role", type === "error" ? "alert" : "status");
  el.textContent = message;

  const remove = () => {
    el.classList.remove("is-visible");
    el.addEventListener("transitionend", () => el.remove(), { once: true });
  };

  el.addEventListener("click", () => {
    clearTimeout(timer);
    remove();
  });

  ensureStack().appendChild(el);
  requestAnimationFrame(() => el.classList.add("is-visible"));

  const timer = setTimeout(remove, 6000);
}
