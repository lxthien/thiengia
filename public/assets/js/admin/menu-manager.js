// Quản lý menu (templates/admin/menu/*.html.twig).
// - Mọi trang: hộp xác nhận cho form có data-menu-confirm.
// - Màn sửa menu: kéo thả (nestable2 qua window.NestableJQuery do menu-editor.js nạp),
//   thêm mục, sửa mục trong hộp thoại. API JSON gửi CSRF qua header X-CSRF-Token.

const TYPE_LABELS = { url: 'Liên kết', page: 'Trang', category: 'Danh mục', news: 'Bài viết' };
const normalize = s => String(s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/đ/gi, 'd').toLowerCase();

// ---------- Hộp xác nhận (xoá menu / xoá mục) ----------
function initConfirm() {
    const dialog = document.getElementById('menu-confirm');
    if (!dialog) return;
    let pending = null;
    let confirmed = null;
    const ask = form => {
        const message = form.dataset.confirmMessage || 'Bạn chắc chắn?';
        if (typeof dialog.showModal !== 'function') {
            if (window.confirm(message)) { confirmed = form; form.requestSubmit(); }
            return;
        }
        pending = form;
        dialog.querySelector('#menu-confirm-title').textContent = form.dataset.confirmTitle || 'Xác nhận';
        dialog.querySelector('#menu-confirm-message').textContent = message;
        dialog.querySelector('#menu-confirm-ok').textContent = form.dataset.confirmButton || 'Xoá';
        dialog.showModal();
        dialog.querySelector('#menu-confirm-cancel').focus();
    };
    document.addEventListener('submit', event => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.matches('[data-menu-confirm]')) return;
        if (confirmed === form) { confirmed = null; return; }
        event.preventDefault();
        ask(form);
    });
    dialog.querySelector('#menu-confirm-cancel').addEventListener('click', () => dialog.close());
    dialog.addEventListener('close', () => { pending = null; });
    dialog.querySelector('#menu-confirm-ok').addEventListener('click', () => {
        if (!pending) return;
        const form = pending;
        confirmed = form;
        dialog.close();
        form.requestSubmit();
    });
    return ask;
}

// ---------- Màn sửa menu ----------
function initEditor(askConfirm) {
    const board = document.getElementById('nestable');
    const $ = window.NestableJQuery;
    if (!board || !$ || !$.fn.nestable) return;

    const config = JSON.parse(board.dataset.menuConfig);
    const root = document.getElementById('menu-items-root');
    const notice = document.querySelector('[data-menu-notice]');
    const status = document.querySelector('[data-menu-status]');
    const empty = document.querySelector('[data-menu-empty]');
    const defaultStatus = status.textContent;

    const showNotice = (message, type = 'success') => {
        notice.hidden = false;
        notice.className = 'menu-notice alert alert-' + (type === 'error' ? 'danger' : 'success');
        notice.textContent = message;
    };
    const setStatus = (text, state = '') => { status.textContent = text; status.dataset.state = state; };

    async function api(url, payload) {
        let response;
        try {
            response = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': config.csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify(payload),
                credentials: 'same-origin',
            });
        } catch (e) {
            throw new Error('Không kết nối được máy chủ. Kiểm tra mạng rồi thử lại.');
        }
        let data = null;
        try { data = await response.json(); } catch (e) { /* không phải JSON (vd trang lỗi) */ }
        if (!response.ok || !data || !data.success) {
            throw new Error((data && data.message) || 'Máy chủ báo lỗi (' + response.status + '). Tải lại trang rồi thử lại.');
        }
        return data;
    }

    // ----- Thứ tự -----
    const serialize = list => Array.from(list.children).filter(li => li.matches('.dd-item')).map(li => {
        const item = { id: Number(li.dataset.id) };
        const children = li.querySelector(':scope > .dd-list');
        if (children && children.children.length) item.children = serialize(children);
        return item;
    });

    let saveTimer = null;
    let saving = Promise.resolve();
    const saveOrder = () => {
        clearTimeout(saveTimer);
        setStatus('Đang lưu thứ tự…', 'saving');
        saveTimer = setTimeout(() => {
            saving = saving.then(() => api(config.reorderUrl, { items: serialize(root) })
                .then(() => setStatus('Đã lưu thứ tự lúc ' + new Date().toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' }) + '.', 'success'))
                .catch(error => { setStatus('Chưa lưu được thứ tự.', 'error'); showNotice(error.message + ' Thứ tự trên màn hình có thể khác với website — hãy tải lại trang.', 'error'); }));
        }, 400);
    };

    const refreshMoveButtons = () => {
        root.querySelectorAll('.dd-item').forEach(li => {
            const siblings = Array.from(li.parentElement.children).filter(n => n.matches('.dd-item'));
            const index = siblings.indexOf(li);
            li.querySelector(':scope > .menu-row__main [data-menu-move="up"]').disabled = index === 0;
            li.querySelector(':scope > .menu-row__main [data-menu-move="down"]').disabled = index === siblings.length - 1;
        });
        const count = root.querySelectorAll('.dd-item').length;
        empty.hidden = count > 0;
        document.querySelectorAll('[data-menu-count]').forEach(el => { el.textContent = count; });
        const deleteMenu = document.querySelector('[data-menu-delete-menu]');
        if (deleteMenu) deleteMenu.dataset.confirmMessage = deleteMenu.dataset.confirmTemplate.replace('__COUNT__', count);
        // Số mục con trong câu xác nhận xoá thay đổi sau khi kéo thả.
        root.querySelectorAll('.dd-item').forEach(li => {
            const form = li.querySelector(':scope > .menu-row__main [data-menu-delete-item]');
            const children = li.querySelectorAll(':scope > .dd-list > .dd-item').length;
            form.dataset.confirmMessage = 'Xoá “' + li.dataset.title + '”' + (children ? ' cùng ' + children + ' mục con' : '') + ' khỏi menu. Nội dung bài viết / trang không bị ảnh hưởng.';
        });
    };

    // SVG thay vì icon font: CSS .dd-item > button toàn cục (_menu-editor.scss) chồng glyph lên icon font.
    const chevron = rotate => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="transform:rotate(' + rotate + 'deg)"><path d="m6 9 6 6 6-6"/></svg>';
    $(board).nestable({
        maxDepth: config.maxDepth,
        expandBtnHTML: '<button type="button" class="menu-toggle" data-action="expand" aria-label="Mở mục con" aria-expanded="false">' + chevron(-90) + '</button>',
        collapseBtnHTML: '<button type="button" class="menu-toggle" data-action="collapse" aria-label="Thu gọn mục con" aria-expanded="true">' + chevron(0) + '</button>',
        callback: () => { refreshMoveButtons(); saveOrder(); },
    });
    refreshMoveButtons();

    document.querySelector('[data-menu-expand]').addEventListener('click', () => $(board).nestable('expandAll'));
    document.querySelector('[data-menu-collapse]').addEventListener('click', () => $(board).nestable('collapseAll'));

    board.addEventListener('click', event => {
        const move = event.target.closest('[data-menu-move]');
        if (move) {
            const li = move.closest('.dd-item');
            const sibling = move.dataset.menuMove === 'up' ? li.previousElementSibling : li.nextElementSibling;
            if (!sibling) return;
            if (move.dataset.menuMove === 'up') sibling.before(li); else sibling.after(li);
            refreshMoveButtons();
            li.querySelector(':scope > .menu-row__main [data-menu-move="' + move.dataset.menuMove + '"]:not(:disabled)')?.focus()
                || li.querySelector(':scope > .menu-row__main [data-menu-edit]').focus();
            saveOrder();
            return;
        }
        const edit = event.target.closest('[data-menu-edit]');
        if (edit) openItem(edit.closest('.dd-item'));
    });

    // ----- Tạo dòng mục mới (sau khi thêm qua API) -----
    function buildRow(item) {
        const li = document.createElement('li');
        li.className = 'dd-item menu-row';
        const main = document.createElement('div');
        main.className = 'menu-row__main';
        main.innerHTML = '<div class="dd-handle menu-grip" title="Kéo để sắp xếp"><i class="fa fa-arrows" aria-hidden="true"></i><span class="sr-only">Kéo để sắp xếp</span></div>'
            + '<div class="menu-row__body"><div class="menu-row__heading"><span class="menu-row__title" data-menu-title></span><span class="menu-type"></span>'
            + '<span class="menu-flag menu-flag--off" data-menu-flag-off hidden>Đang tắt</span><span class="menu-flag" data-menu-flag-blank hidden>Mở tab mới</span></div>'
            + '<span class="menu-row__url" data-menu-url></span></div>'
            + '<div class="menu-row__actions dd-nodrag"><button type="button" class="menu-icon-button" data-menu-move="up"><i class="fa fa-angle-up" aria-hidden="true"></i></button>'
            + '<button type="button" class="menu-icon-button" data-menu-move="down"><i class="fa fa-angle-down" aria-hidden="true"></i></button>'
            + '<button type="button" class="banner-button" data-menu-edit>Sửa</button>'
            + '<form method="post" data-menu-confirm data-menu-delete-item data-confirm-title="Xoá mục menu?" data-confirm-button="Xoá mục"><input type="hidden" name="token"><button type="submit" class="banner-button banner-button--delete">Xoá</button></form></div>';
        li.append(main);
        const type = main.querySelector('.menu-type');
        type.classList.add('menu-type--' + item.type);
        type.textContent = TYPE_LABELS[item.type] || item.type;
        const form = main.querySelector('form');
        form.action = config.deleteUrlBase.replace('__ID__', item.id);
        form.querySelector('input').value = config.deleteToken;
        applyItem(li, item);
        return li;
    }

    // Đồng bộ dữ liệu + phần hiển thị của một dòng với dữ liệu mục từ máy chủ.
    function applyItem(li, item) {
        Object.assign(li.dataset, {
            id: item.id, title: item.title, url: item.url || '', resolvedUrl: item.resolvedUrl || item.url || '',
            type: item.type, target: item.targetAttr || '_self', css: item.cssClass || '', titleattr: item.titleAttr || '', enable: item.enable ? '1' : '0',
        });
        const main = li.querySelector(':scope > .menu-row__main');
        main.querySelector('[data-menu-title]').textContent = item.title;
        main.querySelector('[data-menu-url]').textContent = li.dataset.resolvedUrl || '(chưa có URL)';
        main.querySelector('[data-menu-flag-off]').hidden = !!item.enable;
        main.querySelector('[data-menu-flag-blank]').hidden = item.targetAttr !== '_blank';
        main.querySelector('[data-menu-move="up"]').setAttribute('aria-label', 'Chuyển “' + item.title + '” lên trên');
        main.querySelector('[data-menu-move="down"]').setAttribute('aria-label', 'Chuyển “' + item.title + '” xuống dưới');
        const children = li.querySelectorAll(':scope > .dd-list > .dd-item').length;
        main.querySelector('[data-menu-delete-item]').dataset.confirmMessage = 'Xoá “' + item.title + '”' + (children ? ' cùng ' + children + ' mục con' : '') + ' khỏi menu. Nội dung bài viết / trang không bị ảnh hưởng.';
    }

    async function addItems(payloads, button) {
        if (button) button.disabled = true;
        const added = [];
        const failed = [];
        for (const payload of payloads) {
            try {
                const data = await api(config.addItemUrl, payload);
                board.querySelector(':scope > .dd-empty')?.remove();
                root.append(buildRow(data.item));
                added.push(data.item.title);
                if (payload.item_id) markAdded(payload.type, payload.item_id);
            } catch (error) {
                failed.push((payload.title || payload.url) + ': ' + error.message);
            }
        }
        refreshMoveButtons();
        if (button) button.disabled = false;
        if (failed.length) showNotice((added.length ? 'Đã thêm ' + added.length + ' mục. ' : '') + 'Không thêm được: ' + failed.join(' · '), 'error');
        else showNotice(added.length === 1 ? 'Đã thêm “' + added[0] + '” vào cuối menu.' : 'Đã thêm ' + added.length + ' mục vào cuối menu.');
        return added.length;
    }

    function markAdded(type, id) {
        const box = document.querySelector('[data-menu-source="' + type + '"] input[value="' + id + '"]');
        if (!box) return;
        box.checked = false;
        box.disabled = true;
        const label = box.closest('.menu-source__item');
        label.classList.add('is-added');
        if (!label.querySelector('small')) label.insertAdjacentHTML('beforeend', '<small>Đã có trong menu</small>');
        syncSource(box.closest('[data-menu-source]'));
    }

    // ----- Liên kết tùy chỉnh -----
    const customForm = document.querySelector('[data-menu-custom-link]');
    customForm.addEventListener('submit', async event => {
        event.preventDefault();
        const url = customForm.elements.url.value.trim();
        const title = customForm.elements.title.value.trim();
        if (!url || !title) {
            showNotice('Nhập cả URL và văn bản liên kết.', 'error');
            (url ? customForm.elements.title : customForm.elements.url).focus();
            return;
        }
        if (await addItems([{ type: 'url', url, title }], customForm.querySelector('button[type=submit]'))) customForm.reset();
    });

    // ----- Danh sách trang / danh mục / bài viết -----
    const syncSource = source => {
        const boxes = Array.from(source.querySelectorAll('.menu-source__item input'));
        source.querySelector('[data-menu-add-checked]').disabled = !boxes.some(b => b.checked);
    };
    document.querySelectorAll('[data-menu-source]').forEach(source => {
        const addButton = source.querySelector('[data-menu-add-checked]');
        if (!addButton) return;
        const items = Array.from(source.querySelectorAll('.menu-source__item'));
        source.addEventListener('change', () => syncSource(source));
        source.querySelector('[data-menu-filter]').addEventListener('input', event => {
            const q = normalize(event.target.value.trim());
            let shown = 0;
            items.forEach(item => { item.hidden = q !== '' && !normalize(item.textContent).includes(q); if (!item.hidden) shown++; });
            source.querySelector('[data-menu-none]').hidden = shown > 0;
        });
        source.querySelector('[data-menu-select-all]').addEventListener('click', () => {
            items.filter(item => !item.hidden).forEach(item => { const box = item.querySelector('input'); if (!box.disabled) box.checked = true; });
            syncSource(source);
        });
        addButton.addEventListener('click', () => {
            const checked = items.map(item => item.querySelector('input')).filter(box => box.checked && !box.disabled);
            addItems(checked.map(box => ({ type: source.dataset.menuSource, item_id: Number(box.value), title: box.dataset.title })), addButton);
        });
    });

    // ----- Hộp thoại sửa mục -----
    const dialog = document.getElementById('menu-item-dialog');
    const form = dialog.querySelector('[data-menu-item-form]');
    const error = dialog.querySelector('[data-menu-item-error]');
    let current = null;

    function openItem(li) {
        current = li;
        const d = li.dataset;
        const isLink = d.type === 'url';
        form.elements.title.value = d.title;
        form.elements.url.value = isLink ? d.url : d.resolvedUrl;
        form.elements.url.readOnly = !isLink;
        dialog.querySelector('[data-menu-item-url-help]').textContent = isLink
            ? 'Nhận https://…, mailto:, tel:, #điểm-neo hoặc đường dẫn bắt đầu bằng /.'
            : 'Tự lấy theo ' + (TYPE_LABELS[d.type] || '').toLowerCase() + ' đích — đổi đường dẫn của nội dung thì menu tự cập nhật.';
        form.elements.targetAttr.value = d.target === '_blank' ? '_blank' : '_self';
        form.elements.titleAttr.value = d.titleattr;
        form.elements.cssClass.value = d.css;
        form.elements.enable.checked = d.enable === '1';
        dialog.querySelector('[data-menu-item-source]').textContent = isLink ? 'Liên kết tùy chỉnh' : 'Trỏ tới ' + (TYPE_LABELS[d.type] || d.type).toLowerCase() + ' trên website';
        error.hidden = true;
        dialog.showModal();
        form.elements.title.focus();
    }

    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (!current) return;
        const payload = {
            title: form.elements.title.value.trim(),
            url: form.elements.url.value.trim(),
            targetAttr: form.elements.targetAttr.value,
            titleAttr: form.elements.titleAttr.value.trim(),
            cssClass: form.elements.cssClass.value.trim(),
            enable: form.elements.enable.checked,
        };
        if (!payload.title) { error.hidden = false; error.textContent = 'Tiêu đề hiển thị không được để trống.'; form.elements.title.focus(); return; }
        const submit = form.querySelector('button[type=submit]');
        submit.disabled = true;
        try {
            const data = await api(config.updateUrlBase.replace('__ID__', current.dataset.id), payload);
            applyItem(current, data.item);
            const li = current;
            dialog.close();
            showNotice('Đã lưu “' + data.item.title + '”.');
            li.querySelector(':scope > .menu-row__main [data-menu-edit]').focus();
        } catch (e) {
            error.hidden = false;
            error.textContent = e.message;
        } finally {
            submit.disabled = false;
        }
    });
    dialog.querySelector('[data-menu-item-cancel]').addEventListener('click', () => dialog.close());
    dialog.querySelector('[data-menu-item-delete]').addEventListener('click', () => {
        const deleteForm = current && current.querySelector(':scope > .menu-row__main [data-menu-delete-item]');
        dialog.close();
        if (deleteForm && askConfirm) askConfirm(deleteForm);
    });
    dialog.addEventListener('close', () => { current = null; });
}

const askConfirm = initConfirm();
initEditor(askConfirm);
