/**
 * Cảnh báo khi rời trang (back, đóng tab/trình duyệt, tải lại, bấm link khác)
 * mà form soạn nội dung còn thay đổi chưa lưu.
 *
 * Áp dụng cho form có trình soạn thảo (.txt-ckeditor5 / .txt-ckeditor) và form
 * tự khai báo data-unsaved-guard. Trình duyệt tự hiện hộp thoại xác nhận của
 * nó; nội dung hộp thoại không tùy biến được.
 */
export default function initUnsavedGuard() {
    const forms = Array.from(document.querySelectorAll('form')).filter((form) => (
        form.hasAttribute('data-unsaved-guard') || form.querySelector('textarea.txt-ckeditor5, textarea.txt-ckeditor')
    ));

    if (!forms.length) {
        return;
    }

    const dirtyForms = new Set();
    let submitting = false;

    forms.forEach((form) => {
        const markDirty = () => dirtyForms.add(form);

        form.addEventListener('input', markDirty);
        form.addEventListener('change', markDirty);
        // CKEditor 5 không phát input/change trên textarea gốc; ckeditor5.js
        // phát sự kiện này mỗi khi nội dung editor đổi.
        form.addEventListener('editor:change', markDirty);

        form.addEventListener('submit', (event) => {
            submitting = true;
            // Listener khác (vd Page Builder) có thể chặn submit sau listener
            // này: khi đó vẫn đang ở lại trang nên phải cảnh báo tiếp.
            window.setTimeout(() => {
                if (event.defaultPrevented) {
                    submitting = false;
                }
            }, 0);
        });
    });

    // CKEditor 4 (rich-text trong Page Builder) được tạo động sau khi tải trang.
    if (window.CKEDITOR) {
        window.CKEDITOR.on('instanceReady', (event) => {
            const form = event.editor.element && event.editor.element.$ ? event.editor.element.$.closest('form') : null;
            if (form && forms.includes(form)) {
                event.editor.on('change', () => dirtyForms.add(form));
            }
        });
    }

    window.addEventListener('beforeunload', (event) => {
        if (submitting || dirtyForms.size === 0) {
            return;
        }

        event.preventDefault();
        event.returnValue = '';
    });

    // Quay lại trang từ bfcache sau một lần submit: bật lại cảnh báo.
    window.addEventListener('pageshow', () => {
        submitting = false;
    });
}
