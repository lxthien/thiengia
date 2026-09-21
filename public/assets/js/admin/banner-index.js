// Banner workspace. Existing form routes remain usable without the panels.
(function () {
    const $n = window.NestableJQuery;
    const editor = document.querySelector('#banner-editor-dialog');
    if (!editor) return;
    const content = editor.querySelector('[data-banner-panel-content]');
    const panelError = editor.querySelector('[data-banner-panel-error]');
    const confirmation = document.querySelector('#banner-confirm-dialog');
    const media = document.querySelector('#banner-media-dialog');
    let dirty = false;
    let panelLoading = false;
    let mutationBusy = false;
    let openedHref = '';
    let panelRequest = null;
    let mediaRequest = null;
    let pendingDelete = null;
    let updateControls = () => {};
    const manager = () => document.querySelector('[data-banner-manager]');

    async function request(url, options = {}) {
        const controller = options.controller || new AbortController();
        const timeout = setTimeout(() => controller.abort(), 20000);
        try {
            const response = await fetch(url, {...options, controller: undefined, signal: controller.signal,
                headers: {'X-Requested-With': 'XMLHttpRequest', ...options.headers}});
            if (response.redirected && new URL(response.url).pathname === '/login') {
                throw new Error('Phiên đăng nhập đã hết hạn. Vui lòng tải lại trang.');
            }
            return response;
        } finally {
            clearTimeout(timeout);
        }
    }

    async function jsonRequest(url, options) {
        const response = await request(url, options);
        if (!(response.headers.get('content-type') || '').includes('application/json')) {
            throw new Error('Không nhận được phản hồi hợp lệ. Vui lòng tải lại trang để kiểm tra.');
        }
        const data = await response.json();
        if (!response.ok || data.success !== true) throw new Error(data.message || 'Không lưu được thay đổi.');
        return data;
    }

    function notice(message, error = false) {
        const box = manager().querySelector('[data-banner-notice]');
        box.textContent = message;
        box.hidden = false;
        box.dataset.state = error ? 'error' : 'success';
    }

    function busy(value) {
        mutationBusy = value;
        manager().setAttribute('aria-busy', String(value));
        updateControls();
    }

    async function refresh(result = {}) {
        const url = new URL(location.href);
        if (result.selectCategory) {
            url.searchParams.set('category', result.selectCategory);
            url.searchParams.set('zone', result.zone);
            url.searchParams.delete('q');
            url.searchParams.delete('status');
        }
        if (String(result.deletedCategory) === url.searchParams.get('category')) url.searchParams.delete('category');
        const response = await request(url);
        if (!response.ok) throw new Error('Đã xử lý yêu cầu nhưng chưa tải lại được danh sách. Vui lòng tải lại trang.');
        const html = new DOMParser().parseFromString(await response.text(), 'text/html');
        const next = html.querySelector('[data-banner-manager]');
        if (!next) throw new Error('Không tải được danh sách. Vui lòng tải lại trang.');
        const scroll = window.scrollY;
        const oldBoard = manager().querySelector('#banner-nestable');
        if ($n && $n(oldBoard).data('nestable')) $n(oldBoard).nestable('destroy');
        manager().replaceWith(next);
        history.replaceState(null, '', url);
        mount();
        window.scrollTo(0, scroll);
        if (result.message) notice(result.message);
    }

    function setPanelError(message) {
        panelError.textContent = message;
        panelError.hidden = !message;
    }

    function focusPanel() {
        const field = content.querySelector('.has-error input, .is-invalid, input:not([type=hidden]), select, textarea');
        if (field) field.focus();
    }

    function renderForm(html) {
        const fragment = new DOMParser().parseFromString(html, 'text/html');
        const form = fragment.querySelector('[data-banner-panel-form]');
        if (!form) throw new Error('Không tải được biểu mẫu. Vui lòng tải lại trang.');
        content.innerHTML = fragment.body.innerHTML;
        editor.querySelector('#banner-editor-title').textContent = content.querySelector('[data-panel-heading]').dataset.panelHeading;
        focusPanel();
    }

    async function openPanel(link) {
        if (mutationBusy) return;
        openedHref = link.href;
        const url = new URL(link.href);
        url.searchParams.set('_panel', '1');
        editor.classList.toggle('banner-editor-dialog--category', link.dataset.panelKind === 'category');
        dirty = false;
        panelLoading = true;
        setPanelError('');
        content.textContent = 'Đang tải biểu mẫu…';
        editor.querySelector('#banner-editor-title').textContent = link.dataset.panelKind === 'category' ? 'Nhóm banner' : 'Banner';
        editor.showModal();
        panelRequest = new AbortController();
        try {
            const response = await request(url, {controller: panelRequest});
            if (!response.ok) throw new Error('Không tải được biểu mẫu.');
            renderForm(await response.text());
        } catch (error) {
            if (editor.open) setPanelError(error.message || 'Không tải được biểu mẫu.');
        } finally {
            panelLoading = false;
        }
    }

    function closePanel() {
        if (mutationBusy) return;
        if (dirty && !window.confirm('Bạn có thay đổi chưa lưu. Đóng và bỏ các thay đổi này?')) return;
        panelRequest?.abort();
        dirty = false;
        editor.close();
    }

    editor.addEventListener('cancel', (event) => { event.preventDefault(); closePanel(); });
    editor.addEventListener('input', () => { if (!panelLoading) dirty = true; });
    editor.addEventListener('change', () => { if (!panelLoading) dirty = true; });
    window.addEventListener('beforeunload', (event) => {
        if (!dirty) return;
        event.preventDefault();
        event.returnValue = '';
    });

    async function savePanel(form) {
        if (mutationBusy) return;
        busy(true);
        const button = form.querySelector('[type=submit]');
        button.disabled = true;
        button.textContent = 'Đang lưu…';
        setPanelError('');
        try {
            const response = await request(form.action, {method: 'POST', body: new FormData(form)});
            if ((response.headers.get('content-type') || '').includes('application/json')) {
                const result = await response.json();
                if (!response.ok || result.success !== true) throw new Error(result.message || 'Không lưu được thay đổi.');
                dirty = false;
                editor.close();
                await refresh(result);
                const link = Array.from(manager().querySelectorAll('[data-banner-panel]')).find((a) => a.href === openedHref);
                link?.focus({preventScroll: true});
            } else if (response.status === 422) {
                renderForm(await response.text());
                setPanelError('Vui lòng kiểm tra các trường được đánh dấu.');
            } else throw new Error('Không xác nhận được kết quả lưu. Vui lòng tải lại trang để kiểm tra.');
        } catch (error) {
            const message = error.name === 'AbortError' ? 'Chưa nhận được phản hồi. Hãy tải lại trang để kiểm tra trước khi lưu lại.' : error.message;
            if (editor.open) setPanelError(message); else notice(message, true);
        } finally {
            busy(false);
            if (button.isConnected) { button.disabled = false; button.textContent = 'Lưu thay đổi'; }
        }
    }

    function openDelete(button) {
        pendingDelete = {url: button.dataset.bannerDeleteUrl, token: button.dataset.deleteToken};
        const category = button.dataset.deleteKind === 'category';
        confirmation.querySelector('#banner-confirm-title').textContent = category ? 'Xóa nhóm banner?' : 'Xóa banner?';
        confirmation.querySelector('#banner-confirm-description').textContent = category
            ? 'Xóa nhóm “' + button.dataset.deleteName + '”? Chỉ nhóm không còn banner mới được xóa.'
            : 'Xóa banner “' + button.dataset.deleteName + '”? Ảnh trong thư viện media vẫn được giữ lại.';
        confirmation.querySelector('[data-confirm-error]').hidden = true;
        confirmation.showModal();
    }

    async function confirmDelete() {
        if (mutationBusy || !pendingDelete) return;
        busy(true);
        const button = confirmation.querySelector('[data-confirm-submit]');
        button.disabled = true;
        try {
            const result = await jsonRequest(pendingDelete.url, {method: 'POST', body: new URLSearchParams({token: pendingDelete.token})});
            confirmation.close();
            await refresh(result);
        } catch (error) {
            if (confirmation.open) {
                const box = confirmation.querySelector('[data-confirm-error]');
                box.textContent = error.message;
                box.hidden = false;
            } else notice(error.message, true);
        } finally { busy(false); button.disabled = false; }
    }
    confirmation.addEventListener('cancel', (event) => { if (mutationBusy) event.preventDefault(); });

    async function changeVisibility(button) {
        busy(true);
        button.disabled = true;
        try {
            const result = await jsonRequest(button.dataset.bannerVisibility, {method: 'POST',
                body: new URLSearchParams({token: button.dataset.token, enable: button.dataset.enable})});
            await refresh(result);
        } catch (error) { notice(error.message, true); }
        finally { busy(false); button.disabled = false; }
    }

    function chooseImage(url) {
        const field = content.querySelector('[data-banner-image-field]');
        if (!field) return;
        field.value = url;
        const image = content.querySelector('[data-panel-image-preview]');
        if (url) image.src = url; else image.removeAttribute('src');
        image.hidden = !url;
        content.querySelector('[data-panel-image-empty]').hidden = Boolean(url);
        dirty = true;
    }

    async function loadMedia() {
        mediaRequest?.abort();
        const controller = new AbortController();
        mediaRequest = controller;
        const status = media.querySelector('[data-media-status]');
        const grid = media.querySelector('[data-media-grid]');
        const folder = media.querySelector('[data-media-folder]');
        const url = new URL(media.dataset.pickerUrl, location.origin);
        url.searchParams.set('folder', folder.value);
        status.textContent = 'Đang tải ảnh…';
        grid.replaceChildren();
        try {
            const response = await request(url, {controller});
            if (!response.ok) throw new Error('Không tải được thư viện ảnh.');
            const data = await response.json();
            if (controller.signal.aborted) return;
            const current = folder.value;
            folder.replaceChildren(new Option('Tất cả thư mục', ''));
            data.folders.forEach((name) => folder.add(new Option(name, name)));
            folder.value = current;
            data.files.forEach((file) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'banner-media-item';
                button.dataset.filename = file.filename.toLocaleLowerCase();
                const image = document.createElement('img');
                image.src = file.thumb;
                image.alt = file.filename;
                image.loading = 'lazy';
                image.addEventListener('error', () => { image.src = file.url; }, {once: true});
                const label = document.createElement('span');
                label.textContent = file.filename;
                button.append(image, label);
                button.addEventListener('click', () => { chooseImage(file.url); media.close(); });
                grid.append(button);
            });
            status.textContent = data.files.length ? 'Bấm vào ảnh để chọn; thay đổi chỉ được lưu khi bấm Lưu banner.' : 'Chưa có ảnh trong thư mục này.';
            filterMedia();
        } catch (error) { if (!controller.signal.aborted) status.textContent = error.message; }
    }

    function filterMedia() {
        const term = media.querySelector('[data-media-search]').value.toLocaleLowerCase().trim();
        media.querySelectorAll('.banner-media-item').forEach((item) => { item.hidden = !item.dataset.filename.includes(term); });
    }
    media.querySelector('[data-media-search]').addEventListener('input', filterMedia);
    media.querySelector('[data-media-folder]').addEventListener('change', loadMedia);
    media.addEventListener('close', () => mediaRequest?.abort());

    function mount() {
        const board = manager().querySelector('#banner-nestable');
        manager().querySelectorAll('[data-banner-image]').forEach((image) => {
            const fallback = () => {
                if (!image.dataset.triedOriginal) {
                    image.dataset.triedOriginal = 'true';
                    image.src = image.dataset.originalSrc;
                } else {
                    image.hidden = true;
                    image.parentElement.querySelector('.banner-preview__fallback').hidden = false;
                }
            };
            image.addEventListener('error', fallback);
            if (image.complete && !image.naturalWidth) fallback();
        });
        const list = board.querySelector('#banner-list');
        const items = () => Array.from(list.children).filter((item) => item.classList.contains('dd-item'));
        const getIds = () => items().map((item) => Number(item.dataset.id));
        let savedOrder = getIds();
        const enabled = board.dataset.reorderEnabled === 'true';
        const status = manager().querySelector('[data-banner-status]');
        const announce = (message, state) => { status.textContent = message; status.dataset.state = state; };
        updateControls = () => {
            const rows = items();
            rows.forEach((row, index) => {
                const position = row.querySelector('.banner-position');
                position.textContent = enabled ? '#' + (index + 1) : '—';
                position.setAttribute('aria-label', enabled ? 'Vị trí ' + (index + 1) : 'Đang lọc danh sách');
                row.querySelector('[data-banner-move="up"]').disabled = mutationBusy || !enabled || index === 0;
                row.querySelector('[data-banner-move="down"]').disabled = mutationBusy || !enabled || index === rows.length - 1;
            });
            board.dataset.saving = String(mutationBusy);
            board.setAttribute('aria-busy', String(mutationBusy));
        };
        const restore = () => {
            const rows = new Map(items().map((row) => [Number(row.dataset.id), row]));
            savedOrder.forEach((id) => { if (rows.has(id)) list.appendChild(rows.get(id)); });
        };
        async function persistOrder() {
            if (mutationBusy || !enabled) return;
            const nextOrder = getIds();
            if (JSON.stringify(nextOrder) === JSON.stringify(savedOrder)) { updateControls(); return; }
            busy(true);
            announce('Đang lưu thứ tự…', 'saving');
            try {
                await jsonRequest(board.dataset.reorderUrl, {method: 'POST', headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({items: nextOrder, expected: savedOrder, zone: board.dataset.zone, token: board.dataset.reorderToken})});
                savedOrder = nextOrder;
                announce('Đã lưu thứ tự hiển thị.', 'success');
            } catch (error) {
                restore();
                announce(error.message + ' Đã khôi phục thứ tự trên màn hình; tải lại để kiểm tra.', 'error');
            } finally { busy(false); }
        }
        if (enabled && savedOrder.length && $n?.fn.nestable) $n(board).nestable({maxDepth: 1}).on('change', persistOrder);
        board.addEventListener('click', (event) => {
            const button = event.target.closest('[data-banner-move]');
            if (!button || button.disabled || mutationBusy || !enabled) return;
            const row = button.closest('.dd-item');
            if (button.dataset.bannerMove === 'up' && row.previousElementSibling) list.insertBefore(row, row.previousElementSibling);
            else if (button.dataset.bannerMove === 'down' && row.nextElementSibling) list.insertBefore(row.nextElementSibling, row);
            else return;
            persistOrder();
        });
        updateControls();
    }

    document.addEventListener('click', (event) => {
        const target = event.target;
        const panelLink = target.closest('[data-banner-panel]');
        if (panelLink && !event.ctrlKey && !event.metaKey && !event.shiftKey) {
            event.preventDefault(); openPanel(panelLink); return;
        }
        if (target.closest('[data-banner-panel-close]')) { closePanel(); return; }
        if (target.closest('[data-confirm-close]')) { if (!mutationBusy) confirmation.close(); return; }
        if (target.closest('[data-confirm-submit]')) { confirmDelete(); return; }
        if (target.closest('[data-media-close]')) { media.close(); return; }
        if (target.closest('[data-media-refresh]')) { loadMedia(); return; }
        if (target.closest('[data-banner-choose-image]')) { media.showModal(); loadMedia(); return; }
        if (target.closest('[data-banner-clear-image]')) { chooseImage(''); return; }
        if (mutationBusy) return;
        const remove = target.closest('[data-banner-delete-url]');
        if (remove && !remove.disabled) { openDelete(remove); return; }
        const visibility = target.closest('[data-banner-visibility]');
        if (visibility) changeVisibility(visibility);
    });
    document.addEventListener('submit', (event) => {
        if (event.target.matches('[data-banner-panel-form]')) { event.preventDefault(); savePanel(event.target); }
        else if (mutationBusy && manager().contains(event.target)) event.preventDefault();
    });
    document.addEventListener('change', (event) => {
        if (event.target.matches('[data-banner-filter-select]') && !mutationBusy) event.target.form.requestSubmit();
    });
    mount();
})();
