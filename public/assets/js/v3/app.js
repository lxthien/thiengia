// ============================================================
// Entry JS theme v3 — build/js/v3.js
// Chỉ khởi tạo; mỗi hành vi nằm ở một module riêng và tự thoát
// nếu không tìm thấy DOM tương ứng (an toàn khi dùng chung entry).
// Vanilla JS — không phụ thuộc jQuery của theme cũ.
// ============================================================

import initHeader from "./modules/header";
import initMobileMenu from "./modules/mobile-menu";
import initReveal from "./modules/reveal";
import initCounters from "./modules/counters";
import initHeroSlider from "./modules/hero-slider";
import initQuotes from "./modules/quotes";
import initGallery from "./modules/gallery-lightbox";
import initToc from "./modules/toc";
import initContentToc from "./modules/content-toc";
import initForms from "./modules/forms";
import initRating from "./modules/rating";

document.addEventListener("DOMContentLoaded", () => {
  initHeader();
  initMobileMenu();
  initReveal();
  initCounters();
  initHeroSlider();
  initQuotes();
  initGallery();
  initToc();
  initContentToc();
  initForms();
  initRating();
});
