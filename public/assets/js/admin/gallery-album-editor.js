// Album image workspace. Explicit saves preserve drafts in other rows.
(function () {
    const root = document.querySelector('[data-album-editor]');
    if (!root) return;
    const board = document.querySelector('#album-image-nestable'), list = document.querySelector('#album-image-list');
    const media = document.querySelector('#album-media-dialog'), removeDialog = document.querySelector('#album-delete-dialog');
    const preview = document.querySelector('#album-preview-dialog'), $n = window.NestableJQuery;
    let busy = false, infoDirty = false, pendingRow = null, mediaRequest = null;
    const selected = new Map();
    let uploadQueue = [];
    const rows = () => Array.from(list.querySelectorAll(':scope > .dd-item'));
    const ids = () => rows().map((row) => Number(row.dataset.id));
    let savedOrder = ids();
    const hasDrafts = () => infoDirty || rows().some((row) => row.dataset.dirty === 'true');
    function notice(message, error = false) {
        const box = root.querySelector('[data-album-notice]');
        box.textContent = message; box.hidden = false; box.dataset.state = error ? 'error' : 'success';
    }
    function rowStatus(row, message, state = '') {
        const box = row.querySelector('[data-image-status]'); box.textContent = message; box.dataset.state = state;
    }
    function controls() {
        const all = rows();
        root.setAttribute('aria-busy', String(busy)); board.dataset.saving = String(busy);
        root.querySelectorAll('input, textarea, select, button').forEach((field) => { field.disabled = busy; });
        all.forEach((row, index) => {
            row.querySelector('.banner-position').textContent = '#' + (index + 1);
            row.querySelector('[data-cover-badge]').hidden = index !== 0;
            row.querySelector('[data-image-cover]').hidden = index === 0;
            row.querySelector('[data-image-move=up]').disabled = busy || index === 0;
            row.querySelector('[data-image-move=down]').disabled = busy || index === all.length - 1;
            row.querySelector('[data-image-save]').disabled = busy || row.dataset.dirty !== 'true';
            row.querySelector('[data-preview-url]').disabled = busy || !row.dataset.imageUrl;
        });
        root.querySelector('[data-image-count]').textContent = all.length + ' ảnh';
        const missing = all.filter((row) => !row.dataset.savedAlt.trim()).length;
        root.querySelector('[data-alt-count]').textContent = missing ? missing + ' ảnh chưa có alt' : 'Các ảnh đã có alt';
        root.querySelector('[data-image-empty]').hidden = all.length > 0;
        media.querySelectorAll('input, select, button').forEach(field => { field.disabled = busy; });
        media.querySelectorAll('[data-media-choice]').forEach(button => { button.disabled = busy || rows().some(row => row.dataset.imageUrl === button.dataset.mediaChoice); });
        media.querySelector('[data-media-add]').disabled = busy || !selected.size;
        media.querySelector('[data-media-add]').textContent = selected.size ? 'Thêm ' + selected.size + ' ảnh vào album' : 'Thêm vào album';
        media.querySelector('[data-upload-start]').disabled = busy || !uploadQueue.length;
        removeDialog.querySelector('[data-delete-confirm]').disabled = busy;
    }
    async function request(url, options = {}) {
        const {controller = new AbortController(), ...rest} = options;
        const timer = setTimeout(() => controller.abort(), 20000);
        try {
            const response = await fetch(url, {...rest, signal: controller.signal, headers: {'X-Requested-With': 'XMLHttpRequest', ...rest.headers}});
            if (response.redirected && new URL(response.url).pathname === '/login') throw new Error('Phiên đăng nhập hết hạn. Vui lòng tải lại trang.');
            return response;
        } catch (error) {
            if (error.name === 'AbortError') throw new Error('Chưa nhận được kết quả. Hãy kiểm tra bằng cách tải lại trang trước khi thử lại.');
            throw error;
        } finally { clearTimeout(timer); }
    }
    async function post(url, data) {
        const response = await request(url, {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({token: root.dataset.token, ...data})});
        if (!(response.headers.get('content-type') || '').includes('application/json')) throw new Error('Không xác nhận được kết quả. Vui lòng tải lại trang để kiểm tra.');
        const result = await response.json();
        if (!response.ok || result.success !== true) throw new Error(result.message || 'Không lưu được thay đổi.');
        return result;
    }
    function bindImage(row) {
        const image = row.querySelector('[data-album-image]');
        if (!image) return;
        const fallback = () => {
            if (!image.dataset.triedOriginal) { image.dataset.triedOriginal = 'true'; image.src = image.dataset.originalSrc; }
            else { image.hidden = true; image.parentElement.querySelector('.banner-preview__fallback').hidden = false; }
        };
        image.addEventListener('error', fallback);
        if (image.complete && !image.naturalWidth) fallback();
    }
    function bindOrder() {
        if (!$n?.fn.nestable) return;
        $n(board).off('change.albumImages');
        if ($n(board).data('nestable')) $n(board).nestable('destroy');
        board.querySelectorAll('.dd-empty').forEach((node) => node.remove());
        if (rows().length) $n(board).nestable({maxDepth: 1}).on('change.albumImages', saveOrder);
    }
    async function saveOrder() {
        if (busy) return;
        const next = ids();
        if (JSON.stringify(next) === JSON.stringify(savedOrder)) return;
        busy = true; controls();
        const status = root.querySelector('[data-order-status]'); status.textContent = 'Đang lưu thứ tự…'; status.dataset.state = 'saving';
        try {
            const result = await post(board.dataset.reorderUrl, {items: next, expected: savedOrder});
            savedOrder = next; status.textContent = result.message; status.dataset.state = 'success';
        } catch (error) {
            const map = new Map(rows().map((row) => [Number(row.dataset.id), row]));
            savedOrder.forEach((id) => { if (map.has(id)) list.appendChild(map.get(id)); });
            status.textContent = error.message + ' Đã khôi phục thứ tự trên màn hình.'; status.dataset.state = 'error';
        } finally { busy = false; controls(); }
    }
    async function saveImage(row) {
        if (busy || row.dataset.dirty !== 'true') return;
        const caption = row.querySelector('[name=caption]'), alt = row.querySelector('[name=alt]');
        const data = {caption: caption.value, alt: alt.value, expected: {caption: row.dataset.savedCaption, alt: row.dataset.savedAlt}};
        busy = true; controls(); rowStatus(row, 'Đang lưu…');
        try {
            const result = await post(row.dataset.updateUrl, data);
            caption.value = result.image.caption || ''; alt.value = result.image.alt || '';
            row.dataset.savedCaption = caption.value; row.dataset.savedAlt = alt.value; row.dataset.dirty = 'false';
            const image = row.querySelector('[data-album-image]'); if (image) image.alt = alt.value || 'Ảnh trong album';
            rowStatus(row, result.message, 'success');
        } catch (error) { rowStatus(row, error.message, 'error'); }
        finally { busy = false; controls(); }
    }
    async function saveInfo(form) {
        if (busy) return;
        const data = new FormData(form), url = new URL(form.action); url.searchParams.set('_editor', '1');
        busy = true; controls();
        const status = form.querySelector('[data-info-status]'); status.textContent = 'Đang lưu…';
        try {
            const response = await request(url, {method: 'POST', body: data});
            if (response.status === 422) {
                const fresh = new DOMParser().parseFromString(await response.text(), 'text/html').querySelector('[data-album-info-form]');
                if (!fresh) throw new Error('Vui lòng kiểm tra thông tin album.');
                form.replaceWith(fresh);
                const box = fresh.querySelector('[data-info-status]'); box.textContent = 'Vui lòng kiểm tra các trường được đánh dấu.'; box.dataset.state = 'error';
                return;
            }
            if (!(response.headers.get('content-type') || '').includes('application/json')) throw new Error('Không xác nhận được kết quả lưu. Vui lòng tải lại trang để kiểm tra.');
            const result = await response.json();
            if (!response.ok || result.success !== true) throw new Error(result.message || 'Không lưu được album.');
            infoDirty = false; document.querySelector('#album-heading').textContent = result.name;
            document.title = 'Quản lý ảnh · ' + result.name; status.textContent = result.message; status.dataset.state = 'success';
        } catch (error) { status.textContent = error.message; status.dataset.state = 'error'; }
        finally { busy = false; controls(); }
    }
    function selection(file) {
        if (file) {
            if (selected.has(file.url)) selected.delete(file.url);
            else selected.set(file.url, file);
        } else selected.clear();
        renderSelection();
    }
    function renderSelection() {
        const order = Array.from(selected.keys());
        media.querySelector('[data-selected-name]').textContent = selected.size ? 'Đã chọn ' + selected.size + ' ảnh · Thứ tự theo số trên ảnh' : 'Chưa chọn ảnh';
        media.querySelectorAll('[data-media-choice]').forEach(button => {
            const index = order.indexOf(button.dataset.mediaChoice);
            button.setAttribute('aria-pressed', String(index >= 0));
            button.dataset.selectionLabel = index >= 0 ? '✓ Đã chọn · ' + (index + 1) : '';
            button.setAttribute('aria-label', button.textContent + (index >= 0 ? ' · Đã chọn, thứ tự ' + (index + 1) : ''));
        });
        controls();
    }
    async function loadMedia() {
        if (busy) return;
        mediaRequest?.abort(); const controller = new AbortController(); mediaRequest = controller;
        const status = media.querySelector('[data-media-status]'), grid = media.querySelector('[data-media-grid]'), folder = media.querySelector('[data-media-folder]');
        const url = new URL(media.dataset.pickerUrl, location.origin); url.searchParams.set('folder', folder.value);
        status.textContent = 'Đang tải ảnh…'; grid.replaceChildren();
        try {
            const response = await request(url, {controller});
            if (!response.ok) throw new Error('Không tải được thư viện ảnh.');
            const data = await response.json(); if (controller.signal.aborted) return;
            const current = folder.value; folder.replaceChildren(new Option('Tất cả thư mục', ''));
            data.folders.forEach((name) => folder.add(new Option(name, name))); folder.value = current;
            const uploadFolder = media.querySelector('[data-upload-folder]'), previous = uploadFolder.value;
            uploadFolder.replaceChildren(new Option('Thư mục gốc', ''));
            data.folders.forEach(name => uploadFolder.add(new Option(name, name))); uploadFolder.value = previous;
            const existing = new Set(rows().map((row) => row.dataset.imageUrl));
            data.files.forEach((file) => {
                const button = document.createElement('button'); button.type = 'button'; button.className = 'banner-media-item';
                button.dataset.mediaChoice = file.url; button.dataset.filename = file.filename.toLocaleLowerCase(); button.setAttribute('aria-pressed', 'false');
                button.disabled = existing.has(file.url);
                const image = document.createElement('img'); image.src = file.thumb; image.alt = ''; image.loading = 'lazy';
                image.addEventListener('error', () => { image.src = file.url; }, {once: true});
                const label = document.createElement('span'); label.textContent = file.filename + (button.disabled ? ' · Đã có trong album' : '');
                button.append(image, label); button.addEventListener('click', () => { if (!busy) selection(file); }); grid.append(button);
            });
            status.textContent = data.files.length ? 'Ảnh đã có trong album sẽ không được chọn lại.' : 'Thư mục chưa có ảnh.';
            filterMedia();
            renderSelection();
        } catch (error) { if (!controller.signal.aborted) status.textContent = error.message; }
    }
    function filterMedia() {
        const query = media.querySelector('[data-media-search]').value.trim().toLocaleLowerCase();
        const choices = Array.from(media.querySelectorAll('[data-media-choice]'));
        choices.forEach((button) => { button.hidden = !button.dataset.filename.includes(query); });
        if (query) media.querySelector('[data-media-status]').textContent = choices.filter((button) => !button.hidden).length + ' ảnh phù hợp trong danh sách.';
    }
    async function addImage() {
        if (busy || !selected.size) return;
        let addedRow = null;
        busy = true; controls(); const errorBox = media.querySelector('[data-media-error]'); errorBox.hidden = true;
        try {
            for (const file of Array.from(selected.values())) {
            const result = await post(root.dataset.addUrl, {imageUrl: file.url});
            const row = new DOMParser().parseFromString('<ul>' + result.html + '</ul>', 'text/html').querySelector('.album-image-item');
            if (!row) throw new Error('Ảnh có thể đã được thêm. Vui lòng tải lại trang để kiểm tra.');
            list.append(row); bindImage(row); savedOrder = ids(); selected.delete(file.url);
            addedRow = row;
            }
            media.close(); notice('Đã thêm các ảnh vào cuối album theo thứ tự đã chọn.');
        } catch (error) { errorBox.textContent = error.message; errorBox.hidden = false; }
        finally { busy = false; bindOrder(); renderSelection(); if (!media.open) addedRow?.querySelector('[name=caption]').focus({preventScroll: true}); }
    }
    async function uploadImages() {
        if (busy || !uploadQueue.length) return;
        mediaRequest?.abort();
        const folder = media.querySelector('[data-upload-folder]').value;
        const newFolder = media.querySelector('[data-upload-new-folder]').value.trim();
        const status = media.querySelector('[data-upload-status]');
        const errorBox = media.querySelector('[data-media-error]'); errorBox.hidden = true;
        busy = true; controls();
        let count = 0;
        try {
            while (uploadQueue.length) {
                const file = uploadQueue[0];
                status.textContent = 'Đang tải: ' + file.name;
                const data = new FormData(); data.append('file', file); data.append('folder', folder); data.append('newFolder', newFolder);
                const response = await request(media.dataset.uploadUrl, {method: 'POST', body: data});
                const result = await response.json();
                if (!response.ok || result.status !== 'success') throw new Error(result.message || 'Không tải được ảnh.');
                selected.set(result.url, result); uploadQueue.shift(); count++;
            }
        } catch (error) { errorBox.textContent = error.message; errorBox.hidden = false; }
        finally {
            busy = false;
            media.querySelector('[data-upload-files]').value = '';
            status.textContent = 'Đã tải ' + count + ' ảnh. ' + (uploadQueue.length ? 'Còn ' + uploadQueue.length + ' ảnh chưa tải; có thể thử lại hoặc chọn tệp khác.' : 'Các ảnh đã được chọn, bấm Thêm vào album để xác nhận.');
            // Include uploaded images even when the current folder filter differs.
            media.querySelector('[data-media-folder]').value = '';
            renderSelection();
            await loadMedia();
        }
    }
    async function removeImage() {
        if (busy || !pendingRow) return;
        const row = pendingRow; let removed = false; busy = true; controls();
        try {
            const result = await post(row.dataset.deleteUrl, {});
            row.remove(); savedOrder = ids(); bindOrder(); removeDialog.close(); pendingRow = null; notice(result.message);
            removed = true;
        } catch (error) { const box = removeDialog.querySelector('[data-confirm-error]'); box.textContent = error.message; box.hidden = false; }
        finally { busy = false; controls(); if (removed) root.querySelector('[data-open-media]').focus({preventScroll: true}); }
    }
    document.addEventListener('input', (event) => {
        const row = event.target.closest('.album-image-item');
        if (row) {
            row.dataset.dirty = String(row.querySelector('[name=caption]').value !== row.dataset.savedCaption || row.querySelector('[name=alt]').value !== row.dataset.savedAlt);
            rowStatus(row, row.dataset.dirty === 'true' ? 'Có thay đổi chưa lưu' : ''); controls();
        } else if (event.target.closest('[data-album-info-form]')) infoDirty = true;
    });
    document.addEventListener('change', (event) => { if (event.target.closest('[data-album-info-form]')) infoDirty = true; });
    document.addEventListener('submit', (event) => {
        if (event.target.matches('[data-image-form]')) { event.preventDefault(); saveImage(event.target.closest('.album-image-item')); }
        if (event.target.matches('[data-album-info-form]')) { event.preventDefault(); saveInfo(event.target); }
    });
    document.addEventListener('click', (event) => {
        const target = event.target;
        if (target.closest('[data-preview-close]')) { preview.close(); return; }
        if (busy) return;
        if (target.closest('[data-close-media]')) { media.close(); return; }
        if (target.closest('[data-delete-close]')) { removeDialog.close(); return; }
        if (target.closest('[data-open-media]')) { media.querySelector('[data-media-error]').hidden = true; media.showModal(); loadMedia(); return; }
        if (target.closest('[data-media-refresh]')) { loadMedia(); return; }
        if (target.closest('[data-media-clear]')) { selection(null); return; }
        if (target.closest('[data-upload-start]')) { uploadImages(); return; }
        if (target.closest('[data-media-add]')) { addImage(); return; }
        if (target.closest('[data-delete-confirm]')) { removeImage(); return; }
        const row = target.closest('.album-image-item');
        if (!row) return;
        const show = target.closest('[data-preview-url]');
        if (show) {
            const image = preview.querySelector('[data-large-image]'); image.hidden = false;
            preview.querySelector('[data-preview-error]').hidden = true; image.src = show.dataset.previewUrl;
            image.alt = row.dataset.savedAlt || 'Ảnh trong album'; preview.showModal(); return;
        }
        if (target.closest('[data-image-delete]')) {
            pendingRow = row; removeDialog.querySelector('[data-confirm-error]').hidden = true;
            removeDialog.querySelector('[data-delete-description]').textContent = 'Gỡ ảnh #' + row.dataset.id + ' cùng chú thích và alt khỏi album? File ảnh trong thư viện vẫn được giữ lại.' + (row.dataset.dirty === 'true' ? ' Nội dung chưa lưu của ảnh này cũng sẽ bị bỏ.' : '');
            removeDialog.showModal(); return;
        }
        const move = target.closest('[data-image-move]');
        if (move && !move.disabled) {
            if (move.dataset.imageMove === 'up' && row.previousElementSibling) list.insertBefore(row, row.previousElementSibling);
            else if (move.dataset.imageMove === 'down' && row.nextElementSibling) list.insertBefore(row.nextElementSibling, row);
            saveOrder(); return;
        }
        if (target.closest('[data-image-cover]')) { list.prepend(row); saveOrder(); }
    });
    media.querySelector('[data-media-folder]').addEventListener('change', loadMedia);
    media.querySelector('[data-media-search]').addEventListener('input', filterMedia);
    media.querySelector('[data-upload-files]').addEventListener('change', event => {
        uploadQueue = Array.from(event.target.files);
        media.querySelector('[data-upload-status]').textContent = uploadQueue.length + ' ảnh chờ tải.';
        controls();
    });
    media.addEventListener('close', () => { mediaRequest?.abort(); selection(null); });
    [media, removeDialog].forEach((dialog) => dialog.addEventListener('cancel', (event) => { if (busy) event.preventDefault(); }));
    preview.querySelector('[data-large-image]').addEventListener('error', (event) => { event.target.hidden = true; preview.querySelector('[data-preview-error]').hidden = false; });
    window.addEventListener('beforeunload', (event) => {
        if (!hasDrafts() && !busy) return;
        event.preventDefault(); event.returnValue = '';
    });
    if (matchMedia('(max-width: 1399px)').matches) document.querySelector('.album-information').open = false;
    rows().forEach(bindImage); bindOrder(); controls();
})();
