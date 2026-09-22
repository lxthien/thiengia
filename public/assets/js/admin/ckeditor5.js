import {
    ClassicEditor,
    ButtonView,
    Essentials,
    Paragraph,
    Heading,
    Bold,
    Italic,
    Underline,
    Strikethrough,
    Subscript,
    Superscript,
    RemoveFormat,
    List,
    Indent,
    IndentBlock,
    BlockQuote,
    Alignment,
    Link,
    Image,
    ImageUpload,
    ImageToolbar,
    ImageStyle,
    ImageCaption,
    ImageResize,
    ImageTextAlternative,
    CKFinder,
    Table,
    TableToolbar,
    HorizontalLine,
    FontColor,
    FontBackgroundColor,
    FontFamily,
    FontSize,
    SpecialCharacters,
    SpecialCharactersEssentials,
    SourceEditing,
    PasteFromOffice,
    GeneralHtmlSupport,
    IconTableOfContents,
} from 'ckeditor5';
import coreTranslations from 'ckeditor5/translations/vi.js';
// CSS nạp riêng qua addStyleEntry('css/ckeditor5', ...) trong webpack.config.js,
// <link> ở templates/admin/layout.html.twig — theo đúng quy ước hiện có của
// project (css/admin, css/ckeditor-content cũng tách riêng khỏi JS entry).

// ============================================================
// Chèn HTML thô vào editor (dùng chung cho TOC + content-block tool)
// ============================================================

/**
 * Chèn 1 đoạn HTML thô vào vị trí con trỏ hiện tại — tương đương editor.insertHtml()
 * của CKEditor 4 cũ. GeneralHtmlSupport (bật permissive ở baseConfig) đảm bảo các
 * tag/class lạ (div.ka-table-of-contents, section.cms-block-*...) không bị lọc mất
 * khi convert view -> model.
 */
function insertHtmlIntoEditor(editor, html) {
    const viewFragment = editor.data.processor.toView(html);
    const modelFragment = editor.data.toModel(viewFragment);
    editor.model.insertContent(modelFragment);
}

/**
 * Thay thế 1 phần tử model (thường là 1 block content-block/TOC đã chèn trước đó)
 * bằng HTML mới — tương đương editor.insertHtml() sau khi lồng lại attribute của
 * CKEditor 4 cũ, nhưng ở đây model.insertContent() với 1 Range tự xoá nội dung cũ
 * trong range đó trước khi chèn, nên chỉ cần 1 lệnh.
 */
function replaceModelElementWithHtml(editor, modelElement, html) {
    const viewFragment = editor.data.processor.toView(html);
    const modelFragment = editor.data.toModel(viewFragment);

    editor.model.change((writer) => {
        const range = writer.createRangeOn(modelElement);
        editor.model.insertContent(modelFragment, range);
    });
}

// ============================================================
// Plugin TOC — viết lại từ public/assets/ivoryckeditor/plugins/toc/plugin.js
// (CKEditor 4). Sinh đúng HTML/class cũ (<div class="ka-table-of-contents
// collapsed">, #main-toc) để CSS (ckeditor-content.scss) và JS toggle phía
// public (v3/modules/content-toc.js) không cần đổi gì — 2 chỗ đó chỉ dựa vào
// tên class/id trong HTML output, không quan tâm HTML sinh ra từ CKEditor mấy.
// ============================================================

// Bài viết tự có <h1> riêng (tiêu đề bài), nên "Tiêu đề 1" trong CKEditor 5
// (option đầu tiên của dropdown Heading) cố tình trỏ view -> h2, không phải h1
// — đây là mặc định của CKEditor 5, không phải bug. Nhưng nếu dùng nguyên nhãn
// mặc định ("Heading 1" cho ra <h2>) thì gây hiểu nhầm số không khớp thẻ thật,
// nên đặt lại nhãn tiếng Việt cho khớp đúng cấp thẻ HTML thực sự tạo ra.
const HEADING_OPTIONS = [
    { model: 'paragraph', title: 'Đoạn văn', class: 'ck-heading_paragraph' },
    { model: 'heading1', view: 'h2', title: 'Tiêu đề 2', class: 'ck-heading_heading1' },
    { model: 'heading2', view: 'h3', title: 'Tiêu đề 3', class: 'ck-heading_heading2' },
    { model: 'heading3', view: 'h4', title: 'Tiêu đề 4', class: 'ck-heading_heading3' },
    { model: 'heading4', view: 'h5', title: 'Tiêu đề 5', class: 'ck-heading_heading4' },
    { model: 'heading5', view: 'h6', title: 'Tiêu đề 6', class: 'ck-heading_heading5' },
];

// model "heading1" -> view "h2", dùng để: (a) biết tag HTML thật của 1 heading
// khi build mục lục, (b) tính đúng tên attribute GHS cần set (htmlH2Attributes,
// không phải htmlH1Attributes — GHS đặt tên theo tag VIEW, không phải theo số
// trong tên model).
const HEADING_MODEL_TO_VIEW = HEADING_OPTIONS.reduce((map, option) => {
    if (option.view) {
        map[option.model] = option.view;
    }
    return map;
}, {});

function slugifyHeadingText(text) {
    return text.replace(/["“”]/g, '').replace(/[^A-Za-z0-9_-]/g, '+');
}

function collectHeadings(editor) {
    const root = editor.model.document.getRoot();
    const headings = [];

    for (const child of root.getChildren()) {
        const viewTag = HEADING_MODEL_TO_VIEW[child.name];
        if (!viewTag) {
            continue;
        }

        let text = '';
        for (const node of child.getChildren()) {
            if (node.is('$text') || node.is('$textProxy')) {
                text += node.data;
            }
        }
        text = text.trim();

        if (text) {
            headings.push({ element: child, viewTag, text });
        }
    }

    return headings;
}

/**
 * Xoá khối TOC cũ (nếu có) — chỉ giữ tối đa 1 TOC/bài, giống hành vi plugin CK4 cũ.
 * GeneralHtmlSupport biểu diễn <div> lạ thành model element "htmlDiv", class gốc
 * lưu ở attribute "htmlDivAttributes.classes" (mảng string) — xem tài liệu
 * @ckeditor/ckeditor5-html-support, phần "Enabling all HTML features".
 */
function removeExistingToc(writer, editor) {
    const root = editor.model.document.getRoot();

    Array.from(root.getChildren()).forEach((child) => {
        const attrs = child.getAttribute('htmlDivAttributes');
        const classes = attrs && attrs.classes;
        if (Array.isArray(classes) && classes.includes('ka-table-of-contents')) {
            writer.remove(child);
        }
    });
}

function insertToc(editor) {
    const headings = collectHeadings(editor);

    if (headings.length === 0) {
        window.alert('Bài viết chưa có tiêu đề (H1-H6) để tạo mục lục.');
        return;
    }

    editor.model.change((writer) => {
        const usedIds = new Set();

        const items = headings.map((heading) => {
            const baseId = slugifyHeadingText(heading.text) || 'muc';
            let id = baseId;
            let suffix = 2;
            while (usedIds.has(id)) {
                id = `${baseId}-${suffix}`;
                suffix += 1;
            }
            usedIds.add(id);

            // Gắn id lên chính heading để link "#id" trong TOC trỏ đúng chỗ —
            // dùng cơ chế "extend existing feature" của GeneralHtmlSupport, tên
            // attribute suy từ TAG THẬT (htmlH2Attributes cho <h2>...), không
            // phải từ số trong tên model (heading1 có thể ra <h2> chứ không
            // phải <h1> — xem HEADING_MODEL_TO_VIEW).
            const attrKey = `html${heading.viewTag.charAt(0).toUpperCase()}${heading.viewTag.slice(1)}Attributes`;
            const existing = heading.element.getAttribute(attrKey) || {};
            writer.setAttribute(attrKey, {
                ...existing,
                attributes: { ...(existing.attributes || {}), id },
            }, heading.element);

            return { id, viewTag: heading.viewTag, text: heading.text };
        });

        removeExistingToc(writer, editor);

        const itemsHtml = items.map((item) => {
            // Thụt lề theo cấp thẻ thật (h2 = 0, h3 = 1 cấp, ...) — chỉ để
            // hiển thị lồng cấp trong mục lục, không ảnh hưởng gì khác.
            const indent = (parseInt(item.viewTag.slice(1), 10) - 2) * 30;
            const safeText = item.text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
            return `<div style="margin-left:${indent}px"><a href="#${item.id}"><i>${safeText}</i></a></div>`;
        }).join('');

        const tocHtml = `<div class="ka-table-of-contents collapsed"><p id="main-toc"><b><u>Mục lục</u></b></p>${itemsHtml}</div>`;

        insertHtmlIntoEditor(editor, tocHtml);
    });
}

function TocPlugin(editor) {
    editor.ui.componentFactory.add('toc', (locale) => {
        const view = new ButtonView(locale);

        view.set({
            label: 'Chèn mục lục',
            icon: IconTableOfContents,
            tooltip: true,
        });

        view.on('execute', () => {
            insertToc(editor);
            editor.editing.view.focus();
        });

        return view;
    });
}

// ============================================================
// Content-block tool — cầu nối cho admin.js:initContentBlocks() (giữ nguyên
// toàn bộ UI/modal/7 loại block/buildBlockHtml ở đó, chỉ thay phần "đưa HTML
// vào editor" vốn trước đây dùng CKEDITOR.insertHtml/CKEDITOR.NODE_ELEMENT).
// ============================================================

/**
 * Từ 1 DOM node bất kỳ trong editable (nơi user vừa click), đi ngược lên tìm
 * phần tử mang data-cms-block — tương đương findCmsBlockElement() cũ nhưng
 * làm việc trên DOM thật thay vì CKEDITOR.dom.element.
 */
function findCmsBlockDomElement(domNode) {
    let node = domNode;
    if (node && node.nodeType !== Node.ELEMENT_NODE) {
        node = node.parentElement;
    }
    while (node) {
        if (node.hasAttribute && node.hasAttribute('data-cms-block')) {
            return node;
        }
        node = node.parentElement;
    }
    return null;
}

function attachBlockClickListener(editor, onBlockActivate) {
    if (editor._cmsBlockClickAttached) {
        return;
    }
    editor._cmsBlockClickAttached = true;

    const handle = (evt, data) => {
        const blockDomElement = findCmsBlockDomElement(data.domTarget);
        if (!blockDomElement) {
            return;
        }

        const viewElement = editor.editing.view.domConverter.mapDomToView(blockDomElement);
        const modelElement = viewElement && editor.editing.mapper.toModelElement(viewElement);
        if (!modelElement) {
            return;
        }

        data.preventDefault();

        onBlockActivate({
            element: blockDomElement,
            replace(html) {
                replaceModelElementWithHtml(editor, modelElement, html);
            },
        });
    };

    editor.editing.view.document.on('click', handle);
    editor.editing.view.document.on('dblclick', handle);
}

const editorsByTextareaId = new Map();
const pendingBlockClickCallbacks = new Map();
const pendingReadyCallbacks = new Map();

function registerEditor(id, editor) {
    if (!id) {
        return;
    }

    editorsByTextareaId.set(id, editor);

    const pendingCallback = pendingBlockClickCallbacks.get(id);
    if (pendingCallback) {
        attachBlockClickListener(editor, pendingCallback);
        pendingBlockClickCallbacks.delete(id);
    }

    const readyCallbacks = pendingReadyCallbacks.get(id);
    if (readyCallbacks) {
        readyCallbacks.forEach((callback) => callback(editor));
        pendingReadyCallbacks.delete(id);
    }
}

window.KIENTRUC_CKEDITOR5 = {
    /**
     * Chèn HTML tại vị trí con trỏ hiện tại của editor ứng với id textarea gốc
     * (vd "news_contents"). Trả về false nếu chưa có editor CKEditor 5 nào cho
     * id đó (caller tự fallback sang CKEditor 4/textarea thường).
     */
    insertHtml(textareaId, html) {
        const editor = editorsByTextareaId.get(textareaId);
        if (!editor) {
            return false;
        }
        insertHtmlIntoEditor(editor, html);
        return true;
    },

    /**
     * Trả về HTML hiện tại của editor ứng với id textarea gốc, hoặc null nếu
     * chưa có editor CKEditor 5 nào cho id đó (caller tự fallback). Dùng bởi
     * seo-checker.html.twig để đếm từ/tính điểm SEO live.
     */
    getData(textareaId) {
        const editor = editorsByTextareaId.get(textareaId);
        return editor ? editor.getData() : null;
    },

    /**
     * Đăng ký callback được gọi mỗi khi user click/double-click vào 1 phần tử
     * data-cms-block bên trong editor. Callback nhận { element, replace(html) }.
     * Nếu editor chưa sẵn sàng (ClassicEditor.create() còn đang chạy), callback
     * được xếp hàng và gắn lại ngay khi editor tạo xong (xem registerEditor()).
     */
    bindBlockClicks(textareaId, onBlockActivate) {
        const editor = editorsByTextareaId.get(textareaId);
        if (editor) {
            attachBlockClickListener(editor, onBlockActivate);
        } else {
            pendingBlockClickCallbacks.set(textareaId, onBlockActivate);
        }
    },

    /**
     * Gọi callback(editor) ngay khi CKEditor 5 cho id đó đã sẵn sàng — chạy ngay
     * lập tức nếu đã có, hoặc xếp hàng chờ nếu ClassicEditor.create() còn đang
     * chạy dở. Dùng bởi seo-checker.html.twig để gắn listener đếm từ/tính điểm
     * SEO live thay vì chỉ dò DOM bằng MutationObserver.
     */
    onReady(textareaId, callback) {
        const editor = editorsByTextareaId.get(textareaId);
        if (editor) {
            callback(editor);
            return;
        }

        if (!pendingReadyCallbacks.has(textareaId)) {
            pendingReadyCallbacks.set(textareaId, []);
        }
        pendingReadyCallbacks.get(textareaId).push(callback);
    },
};

const baseConfig = {
    licenseKey: 'GPL',
    translations: [coreTranslations],
    language: 'vi',
    plugins: [
        Essentials,
        Paragraph,
        Heading,
        Bold,
        Italic,
        Underline,
        Strikethrough,
        Subscript,
        Superscript,
        RemoveFormat,
        List,
        Indent,
        IndentBlock,
        BlockQuote,
        Alignment,
        Link,
        Image,
        ImageUpload,
        ImageToolbar,
        ImageStyle,
        ImageCaption,
        ImageResize,
        ImageTextAlternative,
        CKFinder,
        Table,
        TableToolbar,
        HorizontalLine,
        FontColor,
        FontBackgroundColor,
        FontFamily,
        FontSize,
        SpecialCharacters,
        SpecialCharactersEssentials,
        SourceEditing,
        PasteFromOffice,
        GeneralHtmlSupport,
        TocPlugin,
    ],
    // CKFinder thật (dùng chung 1 connector đã có sẵn với CKEditor 4 cũ, xem
    // public/assets/cksourceckfinder/ — script ckfinder.js load global trong
    // admin/layout.html.twig). Nút "ckfinder" mở trình duyệt file/thư viện ảnh;
    // uploadUrl cho phép nút "uploadImage" (kéo-thả/dán) upload thẳng qua cùng
    // connector đó, không cần route/adapter riêng.
    ckfinder: {
        uploadUrl: '/assets/cksourceckfinder/ckfinder/core/connector/php/connector.php?command=QuickUpload&type=Files&responseType=json',
    },
    // Toolbar xuống dòng (nhiều hàng) thay vì gom nút tràn vào 1 nút "..." —
    // giống cách CKEditor 4 cũ hiển thị, user quen mắt hơn kiểu này.
    // shouldNotGroupWhenFull: true tắt hẳn cơ chế DynamicGrouping (nút "..."),
    // toolbar quay lại dùng CSS flex-wrap mặc định của CKEditor 5. Tràn ngang cả
    // trang hoá ra không phải do toolbar, mà do .row/.col-* của theme admin là
    // flexbox (Bootstrap 4) — xem .row > [class*="col-"] trong admin.scss.
    toolbar: {
        items: [
            'undo', 'redo', '|',
            'heading', '|',
            'bold', 'italic', 'underline', 'strikethrough', 'subscript', 'superscript', 'removeFormat', '|',
            'fontFamily', 'fontSize', 'fontColor', 'fontBackgroundColor', '|',
            'alignment', 'bulletedList', 'numberedList', 'outdent', 'indent', '|',
            'link', 'blockQuote', 'insertTable', 'horizontalLine', 'specialCharacters', 'toc', '|',
            'uploadImage', 'ckfinder', '|',
            'sourceEditing',
        ],
        shouldNotGroupWhenFull: true,
    },
    heading: {
        options: HEADING_OPTIONS,
    },
    // Tương đương plugin "image2" của CKEditor 4 cũ — ảnh có caption + canh
    // trái/phải/giữa (canh trái/phải bọc chữ quanh ảnh như image2), không
    // phải dialog "image" cơ bản. alignLeft/alignRight ra class
    // image-style-align-left/-right (float+bọc chữ), alignCenter ra
    // image-style-align-center (block, canh giữa) — CSS thật ở
    // shared/_post-content-typography.scss, áp dụng y hệt trên site công khai.
    image: {
        styles: {
            options: ['alignLeft', 'alignCenter', 'alignRight'],
        },
        toolbar: [
            'imageTextAlternative', '|',
            'toggleImageCaption', '|',
            'imageStyle:alignLeft', 'imageStyle:alignCenter', 'imageStyle:alignRight', '|',
            'resizeImage',
        ],
    },
    table: {
        contentToolbar: ['tableColumn', 'tableRow', 'mergeTableCells'],
    },
    // Cho phép giữ nguyên các thẻ/class tự đặt của content-block tool
    // (section.cms-block-*, data-cms-block/data-cms-payload — xem admin.js:
    // buildBlockHtml), TOC/geo-blocks (stat-box, key-takeaways, definition,
    // ka-table-of-contents...) và Page Builder (ka-builder-*) khi mở lại nội
    // dung cũ — sanitize thật sự nằm ở HTMLPurifier phía server khi lưu (xem
    // Doctrine listener gắn ở News::prePersist/preUpdate), GHS ở đây chỉ để
    // editor không tự "dọn sạch" các tag/class lạ đó.
    //
    // figure/figcaption/img: y hệt config.extraAllowedContent của CKEditor 4
    // cũ (allow-all, kể cả ảnh do content-block tool/Page Builder tự chèn với
    // class riêng như ka-builder-image chứ không phải class "image" của
    // CKEditor 5 — nên không bị plugin Image "nhận nhầm"). GeneralHtmlSupport
    // có sẵn tích hợp chính thức cho việc mở rộng ảnh (ImageElementSupport, tự
    // bật kèm GeneralHtmlSupport) nên không xung đột với Image/ImageCaption.
    htmlSupport: {
        allow: [
            {
                name: /^(div|span|section|p|blockquote|table|thead|tbody|tr|th|td|ul|ol|li|h2|h3|h4)$/,
                attributes: true,
                classes: true,
                styles: true,
            },
            { name: 'figure', attributes: true, classes: true },
            { name: 'figcaption', attributes: true, classes: true },
            { name: 'img', attributes: true, classes: true },
            { name: 'a', attributes: ['data-fancybox', 'data-caption'] },
        ],
    },
};

const editorsByForm = new Map();

function trackEditorForForm(editor, sourceElement) {
    const form = sourceElement.closest('form');
    if (!form) {
        return;
    }

    if (!editorsByForm.has(form)) {
        editorsByForm.set(form, new Set());
        form.addEventListener('submit', () => {
            editorsByForm.get(form).forEach((instance) => instance.updateSourceElement());
        });
    }

    editorsByForm.get(form).add(editor);
}

/**
 * Khởi tạo CKEditor 5 cho các textarea .txt-ckeditor5. Từ giai đoạn 2: News/Page
 * (field contents, có TOC + content-block toolbar) cũng dùng chung hàm này.
 * Page Builder (per-block rich-text) vẫn dùng CKEditor 4 tới giai đoạn 3.
 */
export default function initCkeditor5() {
    document.querySelectorAll('textarea.txt-ckeditor5').forEach((textarea) => {
        const minHeight = textarea.dataset.height ? `${textarea.dataset.height}px` : '500px';

        const config = textarea.dataset.editorPreset === 'comment'
            ? { ...baseConfig, toolbar: { items: ['undo', 'redo', '|', 'bold', 'italic', 'link', '|', 'bulletedList', 'numberedList', 'blockQuote', 'removeFormat'], shouldNotGroupWhenFull: false } }
            : baseConfig;
        ClassicEditor
            .create(textarea, config)
            .then((editor) => {
                // data-height của CKEditor 4 cũ là chiều cao CỐ ĐỊNH; ở đây dùng
                // làm chiều cao tối thiểu, khung tự dài thêm theo nội dung (xem
                // rule trong admin.scss). Đặt qua CSS custom property + rule
                // thường (không !important, không set lại mỗi lần UI update) —
                // bản trước dùng inline style !important gắn lại liên tục trên
                // editor.ui.on('update', ...) làm rối lịch tính scroll-vào-vùng-
                // chọn của CKEditor 5 (bấm vào phần tử cuối bài bị nhảy lên đầu).
                // Custom property đặt ở gốc UI nên .ck-source-editing-area (chế
                // độ Source, DOM khác hẳn .ck-editor__editable) cũng thừa hưởng
                // theo, không cần khoá riêng.
                editor.ui.view.element.style.setProperty('--ck-min-height', minHeight);

                trackEditorForForm(editor, textarea);
                registerEditor(textarea.id, editor);
            })
            .catch((error) => {
                // eslint-disable-next-line no-console
                console.error('Không thể khởi tạo CKEditor 5:', error);
            });
    });
}
