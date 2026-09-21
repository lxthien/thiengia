// Testimonial management with dedicated reorder handles and native editing dialogs.
(function () {
    const editor = document.querySelector('#testimonial-editor-dialog');
    if (!editor) return;
    const confirmation = document.querySelector('#testimonial-confirm-dialog');
    const content = editor.querySelector('[data-testimonial-panel-content]');
    const panelError = editor.querySelector('[data-testimonial-panel-error]');
    const $n = window.NestableJQuery;
    const manager = () => document.querySelector('[data-testimonial-manager]');
    let mutationBusy = false, dirty = false, panelRequest = null, openedHref = '', pendingDelete = null, mediaRequest = null;
    const media = document.querySelector('#testimonial-media-dialog');
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
        const box = manager().querySelector('[data-testimonial-notice]');
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
        const next = new DOMParser().parseFromString(await response.text(), 'text/html').querySelector('[data-testimonial-manager]');
        if (!next) throw new Error('Không tải được danh sách. Vui lòng tải lại trang.');
        const scroll = window.scrollY;
        const board = manager().querySelector('#testimonial-nestable');
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
        if (!wrapper?.querySelector('[data-testimonial-panel-form]')) throw new Error('Không tải được biểu mẫu.');
        content.replaceChildren(wrapper);
        editor.querySelector('#testimonial-editor-title').textContent = wrapper.dataset.panelHeading;
        content.querySelector('.is-invalid, .has-error input, input:not([type=hidden]), textarea')?.focus();
    }
    async function openPanel(link) {
        if (mutationBusy) return;
        openedHref = link.href;
        const url = new URL(link.href); url.searchParams.set('_panel', '1');
        dirty = false; panelMessage(''); content.textContent = 'Đang tải biểu mẫu…';
        editor.querySelector('#testimonial-editor-title').textContent = 'Thông tin đánh giá';
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
                if (!response.ok || result.success !== true) throw new Error(result.message || 'Không lưu được đánh giá.');
                dirty = false;
                editor.close(); await refresh(result);
                Array.from(manager().querySelectorAll('[data-testimonial-panel]')).find((a) => a.href === openedHref)?.focus({preventScroll: true});
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
        pendingDelete = {url: button.dataset.testimonialDelete, token: button.dataset.token};
        confirmation.querySelector('#testimonial-confirm-description').textContent =
            'Xóa đánh giá của “' + button.dataset.name + '”? Thao tác không thể hoàn tác. Ảnh đại diện trong thư viện vẫn được giữ lại.';
        confirmation.querySelector('[data-confirm-error]').hidden = true; confirmation.showModal();
    }
    confirmation.addEventListener('cancel', (event) => { if (mutationBusy) event.preventDefault(); });
    async function confirmDelete() {
        if (mutationBusy || !pendingDelete) return;
        busy(true);
        const button = confirmation.querySelector('[data-testimonial-confirm-submit]'); button.disabled = true;
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
        const url = button.dataset.testimonialVisibility;
        try {
            const result = await jsonRequest(url, {method: 'POST', body: new URLSearchParams({token: button.dataset.token, enable: button.dataset.enable})});
            await refresh(result);
            Array.from(manager().querySelectorAll('[data-testimonial-visibility]')).find((b) => b.dataset.testimonialVisibility === url)?.focus({preventScroll: true});
        } catch (error) { notice(error.message, true); }
        finally { busy(false); button.disabled = false; }
    }
    function chooseImage(url) {
        const field = content.querySelector('[data-testimonial-image-field]');
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
            status.textContent = data.files.length ? 'Bấm vào ảnh để chọn; thay đổi chỉ được lưu khi bấm Lưu đánh giá.' : 'Chưa có ảnh trong thư mục này.';
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
        manager().querySelectorAll('[data-testimonial-image]').forEach((image) => {
            const fallback = () => {
                if (!image.dataset.triedOriginal) { image.dataset.triedOriginal = 'true'; image.src = image.dataset.originalSrc; }
                else { image.hidden = true; image.parentElement.querySelector('.banner-preview__fallback').hidden = false; }
            };
            image.addEventListener('error', fallback);
            if (image.complete && !image.naturalWidth) fallback();
        });
        const board = manager().querySelector('#testimonial-nestable'), list = board.querySelector('#testimonial-list');
        const rows = () => Array.from(list.children).filter((row) => row.classList.contains('dd-item'));
        const ids = () => rows().map((row) => Number(row.dataset.id));
        let saved = ids();
        const enabled = board.dataset.reorderEnabled === 'true';
        const status = manager().querySelector('[data-testimonial-status]');
        updateControls = () => {
            const all = rows();
            all.forEach((row, index) => {
                row.querySelector('.banner-position').textContent = enabled ? '#' + (index + 1) : '—';
                row.querySelector('[data-testimonial-move=up]').disabled = mutationBusy || !enabled || index === 0;
                row.querySelector('[data-testimonial-move=down]').disabled = mutationBusy || !enabled || index === all.length - 1;
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
                saved = next; status.textContent = 'Đã lưu thứ tự đánh giá.'; status.dataset.state = 'success';
            } catch (error) {
                const current = new Map(rows().map((row) => [Number(row.dataset.id), row]));
                saved.forEach((id) => { if (current.has(id)) list.appendChild(current.get(id)); });
                status.textContent = error.message + ' Đã khôi phục thứ tự trên màn hình; tải lại để kiểm tra.';
                status.dataset.state = 'error';
            } finally { busy(false); }
        }
        if (enabled && saved.length > 1 && $n?.fn.nestable) $n(board).nestable({maxDepth: 1}).on('change', persistOrder);
        board.addEventListener('click', (event) => {
            const button = event.target.closest('[data-testimonial-move]');
            if (!button || button.disabled || mutationBusy || !enabled) return;
            const row = button.closest('.dd-item');
            if (button.dataset.testimonialMove === 'up' && row.previousElementSibling) list.insertBefore(row, row.previousElementSibling);
            else if (button.dataset.testimonialMove === 'down' && row.nextElementSibling) list.insertBefore(row.nextElementSibling, row);
            else return;
            persistOrder();
        });
        updateControls();
    }
    document.addEventListener('click', (event) => {
        const target = event.target;
        const link = target.closest('[data-testimonial-panel]');
        if (link && !event.ctrlKey && !event.metaKey && !event.shiftKey) { event.preventDefault(); openPanel(link); return; }
        if (target.closest('[data-testimonial-panel-close]')) { closePanel(); return; }
        if (target.closest('[data-testimonial-confirm-close]')) { if (!mutationBusy) confirmation.close(); return; }
        if (target.closest('[data-testimonial-confirm-submit]')) { confirmDelete(); return; }
        if (mutationBusy) return;
        if (target.closest('[data-media-close]')) { media.close(); return; }
        if (target.closest('[data-media-refresh]')) { loadMedia(); return; }
        if (target.closest('[data-testimonial-choose-image]')) { media.showModal(); loadMedia(); return; }
        if (target.closest('[data-testimonial-clear-image]')) { chooseImage(''); return; }
        const remove = target.closest('[data-testimonial-delete]');
        if (remove) { openDelete(remove); return; }
        const toggle = target.closest('[data-testimonial-visibility]');
        if (toggle) visibility(toggle);
    });
    document.addEventListener('submit', (event) => {
        if (event.target.matches('[data-testimonial-panel-form]')) { event.preventDefault(); savePanel(event.target); }
        else if (mutationBusy && manager().contains(event.target)) event.preventDefault();
    });
    mount();
})();
