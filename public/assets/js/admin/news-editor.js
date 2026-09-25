// Form bài viết: lọc nhanh cây danh mục và tóm tắt danh mục đã chọn / danh mục chính.
// Radio "danh mục chính" do form/hierarchical_category_theme.html.twig tạo.
(() => {
 const root = document.querySelector('.news-editor'); if (!root) return;
 const tree = root.querySelector('.category-tree-wrapper'), panel = root.querySelector('[data-category-filter]');
 if (!tree || !panel) return;
 const items = Array.from(tree.querySelectorAll('.category-checkbox-wrapper'));
 const search = panel.querySelector('#news-category-search'), summary = panel.querySelector('#news-category-summary');
 const primary = document.getElementById('news_categoryPrimary');
 const nameOf = el => el.querySelector('.category-label').textContent.trim();
 const normalize = s => s.normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/đ/gi, 'd').toLowerCase();
 panel.hidden = false;
 if (items.length <= 8) search.closest('.news-editor__category-filter').querySelectorAll('label, input').forEach(el => { el.hidden = true; });
 const refresh = () => {
     const checked = items.filter(el => el.querySelector('.category-checkbox').checked);
     const main = checked.find(el => primary && primary.value && el.dataset.categoryId === primary.value) || checked[0];
     summary.textContent = checked.length
         ? 'Đã chọn ' + checked.length + ' danh mục · Danh mục chính: ' + nameOf(main) + (primary && primary.value ? '' : ' (tự động)')
         : 'Chưa chọn danh mục nào — bài viết sẽ không xuất hiện trong trang danh mục.';
 };
 search.addEventListener('input', () => {
     const q = normalize(search.value.trim());
     // Giữ danh mục đã chọn luôn hiển thị để không "mất" lựa chọn khi lọc.
     items.forEach(el => { el.hidden = q !== '' && !normalize(nameOf(el)).includes(q) && !el.querySelector('.category-checkbox').checked; });
 });
 tree.addEventListener('change', () => setTimeout(refresh));
 refresh();
})();
