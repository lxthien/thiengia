const CONFIG = {
    title: {min: 30, max: 60},
    description: {min: 120, max: 160},
    minimumWords: 300,
};

function find(selector) {
    return selector ? document.querySelector(selector) : null;
}

function htmlToText(value) {
    const element = document.createElement('div');
    element.innerHTML = String(value || '');

    return (element.textContent || '').replace(/\s+/g, ' ').trim();
}

function countWords(value) {
    const words = htmlToText(value).match(/[\p{L}\p{N}]+(?:['’\-][\p{L}\p{N}]+)*/gu);

    return words ? words.length : 0;
}

function getEditorValue(field) {
    if (!field) {
        return '';
    }

    if (window.KIENTRUC_CKEDITOR5) {
        const data = window.KIENTRUC_CKEDITOR5.getData(field.id);
        if (data !== null) {
            return data;
        }
    }

    if (window.CKEDITOR && window.CKEDITOR.instances && window.CKEDITOR.instances[field.id]) {
        return window.CKEDITOR.instances[field.id].getData();
    }

    return field.value || field.textContent || '';
}

function fieldValue(field) {
    return field ? String(field.value || '').trim() : '';
}

function checkLength(value, config, label, emptyMessage) {
    if (value.length === 0) {
        return {status: 'error', title: label, desc: emptyMessage, points: 0};
    }

    if (value.length >= config.min && value.length <= config.max) {
        return {status: 'check', title: label, desc: `${value.length} ký tự — nằm trong khoảng ${config.min}-${config.max}.`, points: 20};
    }

    const direction = value.length < config.min ? 'ngắn' : 'dài';
    return {status: 'warning', title: label, desc: `${value.length} ký tự — hơi ${direction}, nên ở khoảng ${config.min}-${config.max}.`, points: 10};
}

function createCheck(check) {
    const item = document.createElement('div');
    item.className = 'seo-check-item';

    const icon = document.createElement('div');
    icon.className = `seo-check-icon ${check.status}`;
    icon.textContent = check.status === 'check' ? '✓' : (check.status === 'warning' ? '!' : '×');

    const content = document.createElement('div');
    content.className = 'seo-check-content';

    const title = document.createElement('p');
    title.className = 'seo-check-title';
    title.textContent = check.title;

    const description = document.createElement('p');
    description.className = 'seo-check-desc';
    description.textContent = check.desc;

    content.append(title, description);
    item.append(icon, content);

    return item;
}

function setupChecker(container) {
    const fields = {
        title: find(container.dataset.seoTitleInput),
        fallbackTitle: find(container.dataset.seoTitleFallbackInput),
        description: find(container.dataset.seoDescriptionInput),
        fallbackDescription: find(container.dataset.seoDescriptionFallbackInput),
        slug: find(container.dataset.seoSlugInput),
        content: find(container.dataset.seoContentInput),
        focus: find(container.dataset.seoFocusInput),
    };
    const toggle = container.querySelector('[data-seo-toggle]');
    const content = container.querySelector('[data-seo-content]');
    const score = container.querySelector('[data-seo-score]');
    const scoreText = container.querySelector('[data-seo-score-text]');
    const checks = container.querySelector('[data-seo-checks]');
    const density = container.querySelector('[data-seo-density]');
    const densityFill = container.querySelector('[data-seo-density-fill]');
    const densityText = container.querySelector('[data-seo-density-text]');
    const tips = container.querySelector('[data-seo-tips]');
    const previewTitle = container.querySelector('[data-seo-preview-title]');
    const previewUrl = container.querySelector('[data-seo-preview-url]');
    const previewDescription = container.querySelector('[data-seo-preview-description]');

    if (!toggle || !content || !score || !checks) {
        return;
    }

    toggle.addEventListener('click', () => {
        const isOpen = content.hidden;
        content.hidden = !isOpen;
        toggle.setAttribute('aria-expanded', String(isOpen));
        toggle.textContent = isOpen ? 'Thu gọn kiểm tra' : 'Mở kiểm tra';
    });

    function values() {
        const title = fieldValue(fields.title) || fieldValue(fields.fallbackTitle);
        const description = fieldValue(fields.description) || fieldValue(fields.fallbackDescription);
        const rawContent = getEditorValue(fields.content);

        return {
            title,
            description,
            slug: fieldValue(fields.slug),
            rawContent,
            plainContent: htmlToText(rawContent),
            focus: fieldValue(fields.focus).toLocaleLowerCase(),
        };
    }

    function updatePreview(data) {
        previewTitle.textContent = data.title || 'Tiêu đề trang';
        previewDescription.textContent = data.description || 'Thêm mô tả SEO để xem trước đoạn hiển thị.';
        previewUrl.textContent = `${window.location.origin}/${data.slug || 'duong-dan'}`;
    }

    function update() {
        const data = values();
        const wordCount = countWords(data.rawContent);
        const checksData = [
            checkLength(data.title, CONFIG.title, 'SEO title', 'Thêm tiêu đề SEO hoặc tiêu đề bài viết.'),
            checkLength(data.description, CONFIG.description, 'Meta description', 'Thêm mô tả SEO cho trang.'),
        ];

        if (!data.slug) {
            checksData.push({status: 'error', title: 'Đường dẫn', desc: 'Thêm tiêu đề để hệ thống tạo đường dẫn.', points: 0});
        } else if (/^[a-z0-9-]+$/.test(data.slug)) {
            checksData.push({status: 'check', title: 'Đường dẫn', desc: 'Slug hợp lệ, ngắn gọn và có thể đọc được.', points: 15});
        } else {
            checksData.push({status: 'warning', title: 'Đường dẫn', desc: 'Chỉ nên dùng chữ thường, số và dấu gạch ngang.', points: 7});
        }

        if (wordCount >= CONFIG.minimumWords) {
            checksData.push({status: 'check', title: 'Độ dài nội dung', desc: `${wordCount} từ — đạt mốc ${CONFIG.minimumWords} từ.`, points: 15});
        } else if (wordCount === 0) {
            checksData.push({status: 'error', title: 'Độ dài nội dung', desc: `Thêm nội dung; mốc tham chiếu là ${CONFIG.minimumWords} từ.`, points: 0});
        } else {
            checksData.push({status: 'warning', title: 'Độ dài nội dung', desc: `${wordCount} từ — mốc tham chiếu là ${CONFIG.minimumWords} từ.`, points: Math.floor((wordCount / CONFIG.minimumWords) * 15)});
        }

        if (data.focus) {
            const places = [
                data.title.toLocaleLowerCase().includes(data.focus) ? 'tiêu đề' : null,
                data.description.toLocaleLowerCase().includes(data.focus) ? 'mô tả' : null,
                data.plainContent.toLocaleLowerCase().includes(data.focus) ? 'nội dung' : null,
            ].filter(Boolean);

            checksData.push({
                status: places.length ? 'check' : 'warning',
                title: 'Từ khóa trọng tâm',
                desc: places.length ? `Có trong ${places.join(', ')}.` : 'Chưa xuất hiện trong tiêu đề, mô tả hoặc nội dung.',
                points: Math.min(15, places.length * 5),
            });

            const contentOccurrences = data.plainContent.toLocaleLowerCase().split(data.focus).length - 1;
            const percentage = wordCount ? (contentOccurrences / wordCount) * 100 : 0;
            density.hidden = false;
            densityFill.style.width = `${Math.min(100, percentage * 50)}%`;
            densityFill.textContent = `${percentage.toFixed(2)}%`;
            densityText.textContent = `${contentOccurrences} lần trong nội dung (${percentage.toFixed(2)}%).`;
        } else {
            density.hidden = true;
        }

        const total = Math.min(100, checksData.reduce((sum, check) => sum + check.points, 0));
        const level = total >= 80 ? 'excellent' : (total >= 60 ? 'good' : (total >= 40 ? 'moderate' : 'poor'));
        const label = total >= 80 ? 'Tốt' : (total >= 60 ? 'Khá' : (total >= 40 ? 'Cần cải thiện' : 'Chưa sẵn sàng'));

        score.className = `seo-score-circle ${level}`;
        score.textContent = String(total);
        scoreText.textContent = `${label} (${total}/100)`;
        checks.replaceChildren(...checksData.map(createCheck));

        const unresolved = checksData.filter((check) => check.status !== 'check');
        tips.replaceChildren();
        const tipTitle = document.createElement('strong');
        tipTitle.textContent = unresolved.length ? 'Cần xem lại trước khi xuất bản:' : 'Nội dung đã đạt các kiểm tra cơ bản.';
        tips.appendChild(tipTitle);
        if (unresolved.length) {
            const list = document.createElement('ul');
            unresolved.forEach((check) => {
                const item = document.createElement('li');
                item.textContent = `${check.title}: ${check.desc}`;
                list.appendChild(item);
            });
            tips.appendChild(list);
        }

        updatePreview(data);
    }

    [fields.title, fields.fallbackTitle, fields.description, fields.fallbackDescription, fields.slug, fields.content, fields.focus]
        .filter(Boolean)
        .forEach((field) => {
            field.addEventListener('input', update);
            field.addEventListener('change', update);
        });

    if (fields.content && window.KIENTRUC_CKEDITOR5) {
        window.KIENTRUC_CKEDITOR5.onReady(fields.content.id, (editor) => {
            editor.model.document.on('change:data', update);
            update();
        });
    }

    if (fields.content && window.CKEDITOR) {
        window.CKEDITOR.on('instanceReady', (event) => {
            if (event.editor.name === fields.content.id) {
                event.editor.on('change', update);
                update();
            }
        });
    }

    update();
}

export default function initSeoCheckers() {
    document.querySelectorAll('[data-seo-checker]').forEach(setupChecker);
}
