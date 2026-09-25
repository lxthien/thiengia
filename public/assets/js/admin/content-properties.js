import { Plugin, ButtonView, findAttributeRange, ModelLiveRange } from 'ckeditor5';

// All edits go through the model. Opening, browsing and cancelling do not edit content.
export function safePropertyUrl(value, image = false) {
    const url = String(value || '').trim();
    if (!url || /[\u0000-\u0020\u007f]/.test(url) || url.includes('\\')) return false;
    if (/^[a-z][a-z\d+.-]*:/i.test(url)) return (image ? /^https?:/i : /^(https?:|mailto:|tel:)/i).test(url);
    return true; // Relative URLs, anchors and protocol-relative URLs.
}
function el(tag, props = {}, text) {
    const node = document.createElement(tag);
    Object.assign(node, props);
    if (text !== undefined) node.textContent = text;
    return node;
}
let serial = 0;
export default class ContentProperties extends Plugin {
    static get pluginName() { return 'ContentProperties'; }
    init() {
        const editor = this.editor;
        for (const [name, label, command, open] of [
            ['imageProperties', 'Thuộc tính ảnh', 'imageTextAlternative', () => this.openImage()],
            ['linkProperties', 'Thuộc tính liên kết', 'link', () => this.openLink()]
        ]) {
            editor.ui.componentFactory.add(name, locale => {
                const button = new ButtonView(locale);
                button.set({ label, withText: true, tooltip: true });
                button.bind('isEnabled').to(editor.commands.get(command), 'isEnabled');
                this.listenTo(button, 'execute', open);
                return button;
            });
        }
        this.doubleClick = event => {
            const target = event.target;
            if (!(target instanceof Element) || target.closest('[data-cms-block]') || editor.isReadOnly) return;
            const image = target.closest('img');
            if (image) {
                const view = editor.editing.view.domConverter.mapDomToView(image);
                const model = view && editor.plugins.get('ImageUtils').getClosestSelectedImageElement(editor.model.document.selection);
                // Selection is normally made on the first click. Resolve the DOM widget as a fallback.
                const widget = image.closest('figure.image') || image;
                const widgetView = editor.editing.view.domConverter.mapDomToView(widget);
                const resolved = widgetView && editor.editing.mapper.toModelElement(widgetView);
                const selected = resolved || model;
                if (selected && ['imageBlock', 'imageInline'].includes(selected.name)) {
                    event.preventDefault(); event.stopImmediatePropagation();
                    editor.model.change(writer => writer.setSelection(selected, 'on'));
                    this.openImage();
                }
                return;
            }
            const anchor = target.closest('a[href]');
            if (anchor) {
                const view = editor.editing.view.domConverter.mapDomToView(anchor);
                if (!view) return;
                const range = editor.editing.mapper.toModelRange(editor.editing.view.createRangeIn(view));
                event.preventDefault(); event.stopImmediatePropagation();
                editor.model.change(writer => writer.setSelection(range));
                this.openLink();
            }
        };
        this.listenTo(editor, 'ready', () => editor.editing.view.getDomRoot().addEventListener('dblclick', this.doubleClick, true));
        if (!window.CKFinder && !document.querySelector('[data-properties-ckfinder]')) {
            const script = document.createElement('script'); script.src = '/assets/cksourceckfinder/ckfinder/ckfinder.js'; script.dataset.propertiesCkfinder = 'true'; document.head.appendChild(script);
        }
    }
    destroy() {
        this.editor.editing.view.getDomRoot()?.removeEventListener('dblclick', this.doubleClick, true);
        this.dialog?.close();
        super.destroy();
    }
    shell(title) {
        this.dialog?.close();
        const dialog = el('dialog', { className: 'cms-properties' });
        const form = el('form', { noValidate: false });
        const heading = el('h2', { id: 'cms-properties-' + (++serial) }, title);
        dialog.setAttribute('aria-labelledby', heading.id);
        const body = el('div', { className: 'cms-properties__body' });
        const error = el('p', { className: 'cms-properties__error', hidden: true });
        error.setAttribute('role', 'alert');
        const footer = el('div', { className: 'cms-properties__actions' });
        const cancel = el('button', { type: 'button' }, 'Hủy');
        const submit = el('button', { type: 'submit', className: 'cms-properties__primary' }, 'Áp dụng');
        footer.append(cancel, submit);
        form.append(heading, body, error, footer); dialog.append(form); document.body.append(dialog);
        this.dialog = dialog;
        cancel.addEventListener('click', () => dialog.close());
        dialog.addEventListener('close', () => { dialog.remove(); if (this.dialog === dialog) this.dialog = null; this.editor.editing.view.focus(); }, { once: true });
        const fail = message => { error.hidden = false; error.textContent = message; };
        const field = (label, value = '', type = 'text') => {
            const wrap = el('label', { className: 'cms-properties__field' });
            const input = el(type === 'textarea' ? 'textarea' : 'input', type === 'textarea' ? { value, rows: 3 } : { type, value });
            wrap.append(el('span', {}, label), input); body.append(wrap); return input;
        };
        const check = (label, checked) => {
            const wrap = el('label', { className: 'cms-properties__check' });
            const input = el('input', { type: 'checkbox', checked });
            wrap.append(input, document.createTextNode(label)); body.append(wrap); return input;
        };
        const button = (label, fn) => { const b = el('button', { type: 'button' }, label); b.addEventListener('click', fn); body.append(b); return b; };
        dialog.showModal();
        return { dialog, form, body, footer, submit, fail, field, check, button };
    }
    browse(ui, input, imageOnly) {
        // CKFinder opens in a separate window so a native modal's focus trap remains intact.
        if (!window.CKFinder) { ui.fail('Trình duyệt tệp chưa sẵn sàng. Bạn có thể nhập URL trực tiếp.'); return; }
        window.CKFinder.popup({
            chooseFiles: true, width: 1000, height: 700,
            ...(imageOnly ? { resourceType: 'Images' } : {}),
            onInit: finder => finder.on('files:choose', evt => {
                const file = evt.data.files.first();
                if (!ui.dialog.open || !file) return;
                const url = file.getUrl();
                if (!safePropertyUrl(url, imageOnly)) { ui.fail('URL tệp không hợp lệ.'); return; }
                input.value = url;
                input.dispatchEvent(new Event('change'));
            })
        });
    }
    openImage() {
        const editor = this.editor;
        const image = editor.plugins.get('ImageUtils').getClosestSelectedImageElement(editor.model.document.selection);
        if (!image || editor.isReadOnly) return;
        const original = Object.fromEntries(image.getAttributes());
        const caption = [...image.getChildren()].find(child => child.name === 'caption');
        const captionText = caption ? [...editor.model.createRangeIn(caption).getItems()].filter(n => n.is('$textProxy')).map(n => n.data).join('') : '';
        const ui = this.shell('Thuộc tính ảnh');
        const src = ui.field('URL ảnh', original.src || ''); src.required = true;
        ui.button('Duyệt máy chủ / tải ảnh lên', () => this.browse(ui, src, true));
        const alt = ui.field('Văn bản thay thế (ALT)', original.alt || '');
        const width = ui.field('Chiều rộng (px)', original.width || '', 'number'); width.min = '1'; width.max = '20000';
        const height = ui.field('Chiều cao (px)', original.height || '', 'number'); height.min = '1'; height.max = '20000';
        const lock = ui.check('Khóa tỷ lệ ảnh', true);
        let ratio = Number(original.width) / Number(original.height);
        let natural = null;
        const preview = el('img', { className: 'cms-properties__preview', alt: 'Xem trước ảnh' });
        const previewNote = el('p', { className: 'cms-properties__hint' });
        ui.body.append(preview, previewNote);
        const loadPreview = () => {
            natural = null;
            if (!safePropertyUrl(src.value, true)) { preview.removeAttribute('src'); previewNote.textContent = 'Nhập URL ảnh hợp lệ để xem trước.'; return; }
            previewNote.textContent = 'Đang tải ảnh xem trước…'; preview.src = src.value.trim();
        };
        preview.onload = () => { natural = { width: preview.naturalWidth, height: preview.naturalHeight }; if (!Number.isFinite(ratio)) ratio = natural.width / natural.height; previewNote.textContent = 'Kích thước gốc: ' + natural.width + ' × ' + natural.height + ' px. Để trống cả hai ô để dùng kích thước tự nhiên.'; };
        preview.onerror = () => { previewNote.textContent = 'Không tải được ảnh xem trước. Kiểm tra URL trước khi áp dụng.'; };
        src.addEventListener('change', loadPreview); loadPreview();
        width.addEventListener('input', () => { if (lock.checked && ratio > 0 && width.value) height.value = Math.round(Number(width.value) / ratio); });
        height.addEventListener('input', () => { if (lock.checked && ratio > 0 && height.value) width.value = Math.round(Number(height.value) * ratio); });
        ui.button('Khôi phục kích thước gốc', () => { width.value = ''; height.value = ''; if (natural) ratio = natural.width / natural.height; });
        const alignLabel = el('label', { className: 'cms-properties__field' });
        const align = el('select');
        for (const [value, label] of [['','Không'],['alignLeft','Trái'],['alignCenter','Giữa'],['alignRight','Phải']]) align.append(el('option', { value }, label));
        align.value = original.imageStyle || '';
        alignLabel.append(el('span', {}, 'Căn lề'), align); ui.body.append(alignLabel);
        const showCaption = ui.check('Hiển thị chú thích dưới ảnh', !!caption);
        const captionInput = ui.field('Chú thích hiển thị', captionText, 'textarea');
        if (image.name === 'imageInline') { showCaption.disabled = true; captionInput.disabled = true; align.disabled = true; }
        ui.form.addEventListener('submit', event => {
            event.preventDefault();
            if (!safePropertyUrl(src.value, true)) { ui.fail('URL ảnh chỉ nhận HTTP(S) hoặc đường dẫn tương đối, không có khoảng trắng.'); return; }
            if (editor.isReadOnly || image.root.rootName === '$graveyard') { ui.fail('Ảnh không còn có thể chỉnh sửa. Hãy đóng và chọn lại.'); return; }
            const dimensionsChanged = width.value !== String(original.width || '') || height.value !== String(original.height || '');
            editor.model.change(writer => {
                writer.setSelection(image, 'on');
                if (src.value.trim() !== original.src) {
                    writer.setAttribute('src', src.value.trim(), image);
                    ['srcset','sizes','sources'].forEach(key => writer.removeAttribute(key, image));
                }
                writer.setAttribute('alt', alt.value, image);
                if (dimensionsChanged) {
                    for (const [key, input] of [['width', width], ['height', height]]) {
                        if (input.value) writer.setAttribute(key, Number(input.value), image);
                        else writer.removeAttribute(key, image);
                    }
                    if (width.value) writer.setAttribute('resizedWidth', width.value + 'px', image);
                    else writer.removeAttribute('resizedWidth', image);
                    writer.removeAttribute('resizedHeight', image);
                }
                if (!align.disabled && align.value !== (original.imageStyle || '')) {
                    if (align.value) editor.execute('imageStyle', { value: align.value });
                    else writer.removeAttribute('imageStyle', image);
                }
                if (!showCaption.disabled) {
                    const current = [...image.getChildren()].find(child => child.name === 'caption');
                    if (showCaption.checked !== !!current) editor.execute('toggleImageCaption');
                    const next = [...image.getChildren()].find(child => child.name === 'caption');
                    if (next && captionInput.value !== captionText) {
                        writer.remove(writer.createRangeIn(next));
                        writer.insertText(captionInput.value, next);
                    }
                }
            });
            ui.dialog.close();
        });
        src.focus();
    }
    // Tên thuộc tính model mà GeneralHtmlSupport dùng để giữ title/target/rel… của <a>.
    // CKEditor 5 v48: link chữ là "htmlA" (bản cũ là "htmlAAttributes" — ghi vào tên
    // cũ thì model có nhưng getData() không xuất ra), link bọc ảnh là "htmlLinkAttributes".
    linkAttributeName(node) {
        if (node.is('element') && ['imageBlock', 'imageInline'].includes(node.name)) return 'htmlLinkAttributes';
        const plugins = this.editor.plugins;
        return plugins.has('GeneralHtmlSupport') ? plugins.get('GeneralHtmlSupport').getGhsAttributeNameForElement('a') : 'htmlA';
    }
    openLink() {
        const editor = this.editor;
        if (editor.isReadOnly || !editor.commands.get('link').isEnabled) return;
        const selection = editor.model.document.selection;
        let range = selection.getFirstRange();
        const href = selection.getAttribute('linkHref') || [...range.getItems()].find(n => n.hasAttribute('linkHref'))?.getAttribute('linkHref') || '';
        if (selection.isCollapsed && href) range = findAttributeRange(selection.getFirstPosition(), 'linkHref', href, editor.model);
        const live = ModelLiveRange.fromRange(range);
        const text = [...range.getItems()].filter(n => n.is('$textProxy')).map(n => n.data).join('');
        const first = [...range.getItems()].find(n => n.hasAttribute('linkHref'));
        const attrs = (first && first.getAttribute(this.linkAttributeName(first)))?.attributes || {};
        const ui = this.shell('Thuộc tính liên kết');
        ui.dialog.addEventListener('close', () => live.detach(), { once: true });
        const url = ui.field('URL, #điểm-neo, mailto: hoặc tel:', href); url.required = true;
        ui.button('Duyệt máy chủ / tải tệp lên', () => this.browse(ui, url, false));
        const display = ui.field('Nội dung hiển thị', text);
        const imageLink = [...range.getItems()].some(n => ['imageBlock','imageInline'].includes(n.name));
        display.disabled = imageLink;
        const title = ui.field('Tiêu đề gợi ý (title)', attrs.title || '');
        const blank = ui.check('Mở trong tab mới', attrs.target === '_blank');
        const nofollow = ui.check('Đánh dấu nofollow', (attrs.rel || '').split(/\s+/).includes('nofollow'));
        ui.body.append(el('p', { className: 'cms-properties__hint' }, 'Giữ nguyên nội dung hiển thị để bảo toàn định dạng chữ. Thay đổi chỉ có hiệu lực khi bấm Áp dụng.'));
        const restore = () => {
            if (editor.isReadOnly || live.root.rootName === '$graveyard') { ui.fail('Liên kết không còn có thể chỉnh sửa. Hãy chọn lại.'); return false; }
            editor.model.change(writer => writer.setSelection(live.toRange())); return true;
        };
        if (href) {
            const unlink = el('button', { type: 'button' }, 'Bỏ liên kết');
            ui.footer.prepend(unlink);
            unlink.addEventListener('click', () => { if (restore()) { editor.execute('unlink'); ui.dialog.close(); } });
        }
        ui.form.addEventListener('submit', event => {
            event.preventDefault();
            if (!safePropertyUrl(url.value)) { ui.fail('Liên kết không hợp lệ. Chỉ nhận HTTP(S), mailto:, tel: hoặc đường dẫn tương đối.'); return; }
            if (!restore()) return;
            editor.model.change(writer => {
                editor.execute('link', url.value.trim(), {}, display.disabled ? undefined : (display.value || undefined));
                const selected = editor.model.document.selection;
                let updatedRange = selected.getFirstRange();
                if (selected.isCollapsed) updatedRange = findAttributeRange(selected.getFirstPosition(), 'linkHref', url.value.trim(), editor.model);
                for (const node of [...updatedRange.getItems()]) {
                    if (!node.hasAttribute('linkHref')) continue;
                    const attributeName = this.linkAttributeName(node);
                    const data = { ...(node.getAttribute(attributeName) || {}) };
                    data.attributes = { ...(data.attributes || {}) };
                    if (title.value) data.attributes.title = title.value; else delete data.attributes.title;
                    if (blank.checked) data.attributes.target = '_blank'; else delete data.attributes.target;
                    const rel = new Set((data.attributes.rel || '').split(/\s+/).filter(Boolean));
                    if (nofollow.checked) rel.add('nofollow'); else rel.delete('nofollow');
                    // noopener/noreferrer chỉ có nghĩa khi mở tab mới — bỏ khi tắt tab mới.
                    if (blank.checked) rel.add('noopener'); else { rel.delete('noopener'); rel.delete('noreferrer'); }
                    if (rel.size) data.attributes.rel = [...rel].join(' '); else delete data.attributes.rel;
                    const target = node.is('$textProxy') ? writer.createRange(writer.createPositionAt(node.parent, node.startOffset), writer.createPositionAt(node.parent, node.endOffset)) : node;
                    if (Object.keys(data.attributes).length || Object.keys(data).length > 1) writer.setAttribute(attributeName, data, target);
                    else writer.removeAttribute(attributeName, target);
                }
            });
            ui.dialog.close();
        });
        url.focus();
    }
}
