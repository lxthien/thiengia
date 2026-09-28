import 'nestable2/dist/jquery.nestable.min.js';

// admin.js và menu-editor.js là 2 webpack entry riêng biệt, mỗi entry tự đóng gói
// 1 bản jQuery độc lập (không share module) — nestable2 chỉ đăng ký .fn.nestable
// lên bản jQuery CỦA FILE NÀY. Trang nào chỉ load admin.js (không load file này)
// sẽ không có .fn.nestable trên `$` toàn cục của nó. Lộ ra 1 tham chiếu riêng để
// các trang có kéo-thả (banner, testimonial, ...) dùng đúng bản có nestable, thay
// vì đoán mò `window.$` — không ghi đè window.$/jQuery để tránh ảnh hưởng đến các
// plugin khác (vd Bootstrap modal) đã gắn sẵn trên bản jQuery của admin.js.
window.NestableJQuery = $;

// Logic màn sửa menu nằm ở menu-manager.js (dùng window.NestableJQuery ở trên).
