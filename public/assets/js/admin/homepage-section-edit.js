// Thêm/xóa dòng cho các danh sách nội dung của khối trang chủ.
// Hợp đồng DOM xem templates/admin/homepage_section/_collection.html.twig.
(function () {
    var blocks = document.querySelectorAll('[data-collection]');
    if (!blocks.length) return;

    Array.prototype.forEach.call(blocks, function (block) {
        var rows = block.querySelector('[data-collection-rows]');
        var empty = block.querySelector('[data-collection-empty]');
        var addButton = block.querySelector('[data-collection-add]');

        function syncEmptyState() {
            if (empty) empty.hidden = rows.children.length > 0;
        }

        function buildRow(html) {
            var row = document.createElement('div');
            row.className = 'section-collection__row';
            row.setAttribute('data-collection-row', '');

            var fields = document.createElement('div');
            fields.className = 'section-collection__fields';
            fields.innerHTML = html;

            var remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'btn btn-default btn-sm section-collection__remove';
            remove.setAttribute('data-collection-remove', '');
            remove.setAttribute('aria-label', 'Xóa dòng này');
            remove.innerHTML = '<i class="fa fa-trash" aria-hidden="true"></i>';

            row.appendChild(fields);
            row.appendChild(remove);
            return row;
        }

        addButton.addEventListener('click', function () {
            // __name__ là placeholder chỉ số dòng của Symfony CollectionType.
            var index = parseInt(block.dataset.index || '0', 10);
            var html = (block.dataset.prototype || '').replace(/__name__/g, String(index));
            if (!html) return;

            block.dataset.index = String(index + 1);
            var row = buildRow(html);
            rows.appendChild(row);
            syncEmptyState();

            var firstInput = row.querySelector('input, select, textarea');
            if (firstInput) firstInput.focus();
        });

        block.addEventListener('click', function (event) {
            var button = event.target.closest('[data-collection-remove]');
            if (!button || !rows.contains(button)) return;

            var row = button.closest('[data-collection-row]');
            if (row) row.remove();
            syncEmptyState();
        });

        syncEmptyState();
    });
})();
