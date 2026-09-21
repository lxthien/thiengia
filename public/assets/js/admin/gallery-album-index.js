// List-level album management; the existing image editor remains a separate route.
(function () {
    const editor = document.querySelector('#gallery-editor-dialog');
    if (!editor) return;
    const confirmation = document.querySelector('#gallery-confirm-dialog');
    const content = editor.querySelector('[data-gallery-panel-content]');
    const panelError = editor.querySelector('[data-gallery-panel-error]');
    const $n = window.NestableJQuery;
    const manager = () => document.querySelector('[data-gallery-manager]');
    let mutationBusy = false, dirty = false, panelRequest = null, openedHref = '', pendingDelete = null;
    let updateControls = () => {};

    async function request(url, options = {}) {
        const {controller = new AbortController(), ...fetchOptions} = options;
        const timeout = setTimeout(() => controller.abort(), 20000);
        try {
            const response = await fetch(url, {...fetchOptions, signal: controller.signal,
                headers: {'X-Requested-With': 'XMLHttpRequest', ...fetchOptions.headers}});
            if (response.redirected && new URL(response.url).pathname === '/login') {
                throw new Error('Phiên đăng nhập đã hết hạn. Vui lòng tải lại trang.');
            }
            return response;
        } catch (error) {
            if (error.name === 'AbortError') throw new Error('Chưa nhận được phản hồi. Hãy tải lại trang để kiểm tra trước khi thử lại.');
            throw error;
        } finally { clearTimeout(timeout); }
    }
    async function jsonRequest(url, options) {
        const response = await request(url, options);
        if (!(response.headers.get('content-type') || '').includes('application/json')) {
            throw new Error('Không xác nhận được kết quả. Vui lòng tải lại trang để kiểm tra.');
        }
        const result = await response.json();
        if (!response.ok || result.success !== true) throw new Error(result.message || 'Không lưu được thay đổi.');
        return result;
    }
    function notice(message, error = false) {
        const box = manager().querySelector('[data-gallery-notice]');
        box.textContent = message; box.hidden = false; box.dataset.state = error ? 'error' : 'success';
    }
    function busy(value) {
        mutationBusy = value;
        manager().setAttribute('aria-busy', String(value));
        updateControls();
    }
    async function refresh(result) {
        const response = await request(location.href);
        if (!response.ok) throw new Error('Đã xử lý yêu cầu nhưng chưa tải lại được danh sách. Vui lòng tải lại trang.');
        const next = new DOMParser().parseFromString(await response.text(), 'text/html').querySelector('[data-gallery-manager]');
        if (!next) throw new Error('Không tải được danh sách. Vui lòng tải lại trang.');
        const scroll = window.scrollY;
        const board = manager().querySelector('#gallery-nestable');
        if ($n && $n(board).data('nestable')) $n(board).nestable('destroy');
        manager().replaceWith(next);
        mount(); window.scrollTo(0, scroll);
        if (result.message) notice(result.message);
    }
    function panelMessage(message) {
        panelError.textContent = message; panelError.hidden = !message;
    }
    function renderForm(html) {
        const doc = new DOMParser().parseFromString(html, 'text/html');
        const wrapper = doc.querySelector('[data-panel-heading]');
        if (!wrapper?.querySelector('[data-gallery-panel-form]')) throw new Error('Không tải được biểu mẫu.');
        content.replaceChildren(wrapper);
        editor.querySelector('#gallery-editor-title').textContent = wrapper.dataset.panelHeading;
        content.querySelector('.is-invalid, .has-error input, input:not([type=hidden]), textarea')?.focus();
    }
    async function openPanel(link) {
        if (mutationBusy) return;
        openedHref = link.href;
        const url = new URL(link.href); url.searchParams.set('_panel', '1');
        dirty = false; panelMessage(''); content.textContent = 'Đang tải biểu mẫu…';
        editor.querySelector('#gallery-editor-title').textContent = 'Thông tin album';
        editor.showModal();
        panelRequest?.abort();
        const controller = new AbortController(); panelRequest = controller;
        try {
            const response = await request(url, {controller});
            if (!response.ok) throw new Error('Không tải được biểu mẫu.');
            const html = await response.text();
            if (controller.signal.aborted || !editor.open) return;
            renderForm(html);
        } catch (error) { if (!controller.signal.aborted && editor.open) panelMessage(error.message); }
    }
    function closePanel() {
        if (mutationBusy) return;
        if (dirty && !window.confirm('Bạn có thay đổi chưa lưu. Đóng và bỏ các thay đổi này?')) return;
        panelRequest?.abort(); dirty = false; editor.close();
    }
    editor.addEventListener('cancel', (event) => { event.preventDefault(); closePanel(); });
    editor.addEventListener('input', () => { dirty = true; });
    editor.addEventListener('change', () => { dirty = true; });
    window.addEventListener('beforeunload', (event) => {
        if (!dirty) return;
        event.preventDefault(); event.returnValue = '';
    });
    async function savePanel(form) {
        if (mutationBusy) return;
        busy(true); panelMessage('');
        const submit = form.querySelector('[type=submit]'), label = submit.textContent;
        const data = new FormData(form);
        const fields = Array.from(form.elements).filter((field) => !field.disabled);
        fields.forEach((field) => { field.disabled = true; }); submit.textContent = 'Đang lưu…';
        try {
            const response = await request(form.action, {method: 'POST', body: data});
            if ((response.headers.get('content-type') || '').includes('application/json')) {
                const result = await response.json();
                if (!response.ok || result.success !== true) throw new Error(result.message || 'Không lưu được album.');
                dirty = false;
                if (result.redirect) {
                    const destination = new URL(result.redirect, location.origin);
                    if (destination.origin !== location.origin || !destination.pathname.startsWith('/admin/gallery-album/')) throw new Error('Đường dẫn không hợp lệ.');
                    location.assign(destination.href); return;
                }
                editor.close(); await refresh(result);
                Array.from(manager().querySelectorAll('[data-gallery-panel]')).find((a) => a.href === openedHref)?.focus({preventScroll: true});
            } else if (response.status === 422) {
                renderForm(await response.text()); panelMessage('Vui lòng kiểm tra các trường được đánh dấu.');
            } else throw new Error('Không xác nhận được kết quả lưu. Vui lòng tải lại trang để kiểm tra.');
        } catch (error) {
            if (editor.open) panelMessage(error.message); else notice(error.message, true);
        } finally {
            busy(false);
            fields.forEach((field) => { field.disabled = false; }); submit.textContent = label;
        }
    }
    function openDelete(button) {
        pendingDelete = {url: button.dataset.galleryDelete, token: button.dataset.token};
        confirmation.querySelector('#gallery-confirm-description').textContent =
            'Xóa album “' + button.dataset.name + '” cùng ' + button.dataset.count +
            ' ảnh và chú thích trong album? Không thể hoàn tác thao tác này. File ảnh gốc trong thư viện media vẫn được giữ lại.';
        confirmation.querySelector('[data-confirm-error]').hidden = true; confirmation.showModal();
    }
    confirmation.addEventListener('cancel', (event) => { if (mutationBusy) event.preventDefault(); });
    async function confirmDelete() {
        if (mutationBusy || !pendingDelete) return;
        busy(true);
        const button = confirmation.querySelector('[data-gallery-confirm-submit]'); button.disabled = true;
        try {
            const result = await jsonRequest(pendingDelete.url, {method: 'POST', body: new URLSearchParams({token: pendingDelete.token})});
            confirmation.close(); await refresh(result);
            manager().querySelector('h1').setAttribute('tabindex', '-1'); manager().querySelector('h1').focus({preventScroll: true});
        } catch (error) {
            if (confirmation.open) {
                const box = confirmation.querySelector('[data-confirm-error]'); box.textContent = error.message; box.hidden = false;
            } else notice(error.message, true);
        } finally { busy(false); button.disabled = false; }
    }
    async function visibility(button) {
        busy(true); button.disabled = true;
        const url = button.dataset.galleryVisibility;
        try {
            const result = await jsonRequest(url, {method: 'POST', body: new URLSearchParams({token: button.dataset.token, enable: button.dataset.enable})});
            await refresh(result);
            Array.from(manager().querySelectorAll('[data-gallery-visibility]')).find((b) => b.dataset.galleryVisibility === url)?.focus({preventScroll: true});
        } catch (error) { notice(error.message, true); }
        finally { busy(false); button.disabled = false; }
    }
    function mount() {
        manager().querySelectorAll('[data-gallery-image]').forEach((image) => {
            const fallback = () => {
                if (!image.dataset.triedOriginal) { image.dataset.triedOriginal = 'true'; image.src = image.dataset.originalSrc; }
                else { image.hidden = true; image.parentElement.querySelector('.banner-preview__fallback').hidden = false; }
            };
            image.addEventListener('error', fallback);
            if (image.complete && !image.naturalWidth) fallback();
        });
        const board = manager().querySelector('#gallery-nestable'), list = board.querySelector('#gallery-list');
        const rows = () => Array.from(list.children).filter((row) => row.classList.contains('dd-item'));
        const ids = () => rows().map((row) => Number(row.dataset.id));
        let saved = ids();
        const enabled = board.dataset.reorderEnabled === 'true';
        const status = manager().querySelector('[data-gallery-status]');
        updateControls = () => {
            const all = rows();
            all.forEach((row, index) => {
                row.querySelector('.banner-position').textContent = enabled ? '#' + (index + 1) : '—';
                row.querySelector('[data-gallery-move=up]').disabled = mutationBusy || !enabled || index === 0;
                row.querySelector('[data-gallery-move=down]').disabled = mutationBusy || !enabled || index === all.length - 1;
            });
            board.dataset.saving = String(mutationBusy);
            board.setAttribute('aria-busy', String(mutationBusy));
        };
        async function persistOrder() {
            if (mutationBusy || !enabled) return;
            const next = ids();
            if (JSON.stringify(saved) === JSON.stringify(next)) { updateControls(); return; }
            busy(true); status.textContent = 'Đang lưu thứ tự…'; status.dataset.state = 'saving';
            try {
                await jsonRequest(board.dataset.reorderUrl, {method: 'POST', headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({items: next, expected: saved, token: board.dataset.reorderToken})});
                saved = next; status.textContent = 'Đã lưu thứ tự album.'; status.dataset.state = 'success';
            } catch (error) {
                const current = new Map(rows().map((row) => [Number(row.dataset.id), row]));
                saved.forEach((id) => { if (current.has(id)) list.appendChild(current.get(id)); });
                status.textContent = error.message + ' Đã khôi phục thứ tự trên màn hình; tải lại để kiểm tra.';
                status.dataset.state = 'error';
            } finally { busy(false); }
        }
        if (enabled && saved.length > 1 && $n?.fn.nestable) $n(board).nestable({maxDepth: 1}).on('change', persistOrder);
        board.addEventListener('click', (event) => {
            const button = event.target.closest('[data-gallery-move]');
            if (!button || button.disabled || mutationBusy || !enabled) return;
            const row = button.closest('.dd-item');
            if (button.dataset.galleryMove === 'up' && row.previousElementSibling) list.insertBefore(row, row.previousElementSibling);
            else if (button.dataset.galleryMove === 'down' && row.nextElementSibling) list.insertBefore(row.nextElementSibling, row);
            else return;
            persistOrder();
        });
        updateControls();
    }
    document.addEventListener('click', (event) => {
        const target = event.target;
        const link = target.closest('[data-gallery-panel]');
        if (link && !event.ctrlKey && !event.metaKey && !event.shiftKey) { event.preventDefault(); openPanel(link); return; }
        if (target.closest('[data-gallery-panel-close]')) { closePanel(); return; }
        if (target.closest('[data-gallery-confirm-close]')) { if (!mutationBusy) confirmation.close(); return; }
        if (target.closest('[data-gallery-confirm-submit]')) { confirmDelete(); return; }
        if (mutationBusy) return;
        const remove = target.closest('[data-gallery-delete]');
        if (remove) { openDelete(remove); return; }
        const toggle = target.closest('[data-gallery-visibility]');
        if (toggle) visibility(toggle);
    });
    document.addEventListener('submit', (event) => {
        if (event.target.matches('[data-gallery-panel-form]')) { event.preventDefault(); savePanel(event.target); }
        else if (mutationBusy && manager().contains(event.target)) event.preventDefault();
    });
    mount();
})();
