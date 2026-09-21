import { rulers } from './lo-ban-data.js';
import { findCung, findGoodBands, parseMeasurement, MAX_MM } from './lo-ban-calculator.js';

// Màu canvas không đọc được biến SCSS — giữ đồng bộ thủ công với
// public/assets/scss/v3/_tokens.scss ($primary, $text, $accent, $mut, $line).
const COLOR_CAT = '#b42318'; // $primary — quy ước thước thật: đỏ = cung tốt
const COLOR_HUNG = '#2b2622'; // $text — đen/nâu than = cung xấu
const COLOR_TICK = '#514b45';
const COLOR_LINE = '#a89e93';
const COLOR_MUTE = '#514b45';
const FONT = '"Be Vietnam Pro", system-ui, sans-serif';

const EDGE_WARN_MM = 2.5;
const SCALE_H = 28;
const BIG_TOP = 28;
const BIG_BOTTOM = 55;
const SMALL_BOTTOM = 99;
const STRIP_BOTTOM = 104;
const ROW_HEIGHT = 105;

const PRESETS = [
    { label: 'Rộng cửa phòng 81cm', cm: 81 },
    { label: 'Rộng cửa sổ 125cm', cm: 125 },
    { label: 'Cao cửa chính 212cm', cm: 212 },
    { label: 'Cao cửa lớn 232cm', cm: 232 },
    { label: 'Cao thông tầng 340cm', cm: 340 },
    { label: 'Bàn thờ treo 107cm', cm: 107 },
];

function prepareRuler(ruler) {
    const bigStep = ruler.cycleMm / ruler.cungLon.length;
    const flatMarks = [];
    ruler.cungLon.forEach((cungLon) => {
        cungLon.cungBe.forEach((cungBe) => {
            flatMarks.push({ ten: cungBe.ten, cat: cungLon.catHung === 'cat' });
        });
    });
    const smallStep = ruler.cycleMm / flatMarks.length;
    return { ...ruler, bigStep, smallStep, flatMarks };
}

const preparedRulers = rulers.map(prepareRuler);

function drawBand(ctx, width, m0, m1, px, step, count, getMark, yTop, yBottom, font) {
    const i0 = Math.max(0, Math.floor(m0 / step));
    const i1 = Math.floor(m1 / step);

    ctx.font = font;
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';

    for (let i = i0; i <= i1; i++) {
        const mark = getMark(((i % count) + count) % count);
        const xa = (i * step - m0) * px;
        const xb = xa + step * px;

        ctx.strokeStyle = COLOR_LINE;
        ctx.lineWidth = 1;
        ctx.beginPath();
        ctx.moveTo(Math.round(xa) + 0.5, yTop);
        ctx.lineTo(Math.round(xa) + 0.5, yBottom);
        ctx.stroke();
        ctx.beginPath();
        ctx.moveTo(0, yBottom + 0.5);
        ctx.lineTo(width, yBottom + 0.5);
        ctx.stroke();

        if (xb < 0 || xa > width) continue;
        ctx.save();
        ctx.beginPath(); ctx.rect(xa + 1, yTop, xb - xa - 2, yBottom - yTop); ctx.clip();
        ctx.fillStyle = mark.cat ? COLOR_CAT : COLOR_HUNG;
        const words = mark.ten.split(' '), lines = [''];
        words.forEach(word => {
            const last = lines.length - 1, next = (lines[last] + ' ' + word).trim();
            if (ctx.measureText(next).width > xb - xa - 10 && lines[last]) lines.push(word);
            else lines[last] = next;
        });
        const lineHeight = 14, start = (yTop + yBottom) / 2 - (lines.length - 1) * lineHeight / 2;
        const labelX = yTop === BIG_TOP ? (Math.max(0, xa) + Math.min(width, xb)) / 2 : (xa + xb) / 2;
        lines.forEach((line, index) => ctx.fillText(line, labelX, start + index * lineHeight));
        ctx.restore();
    }
}

function drawRuler(canvas, ruler, off, px) {
    const width = canvas.clientWidth;
    if (!width) {
        return;
    }
    const dpr = window.devicePixelRatio || 1;
    const targetW = Math.round(width * dpr);
    const targetH = Math.round(ROW_HEIGHT * dpr);
    if (canvas.width !== targetW || canvas.height !== targetH) {
        canvas.width = targetW;
        canvas.height = targetH;
        canvas.style.height = `${ROW_HEIGHT}px`;
    }

    const ctx = canvas.getContext('2d');
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    ctx.fillStyle = '#f8f6f2';
    ctx.fillRect(0, 0, width, ROW_HEIGHT);

    const m0 = off;
    const m1 = off + width / px;

    // Thang chia vạch mm/cm
    ctx.strokeStyle = COLOR_TICK;
    ctx.lineWidth = 1;
    ctx.beginPath();
    for (let m = Math.max(0, Math.floor(m0)); m <= m1; m++) {
        const x = Math.round((m - m0) * px) + 0.5;
        const len = m % 10 === 0 ? 12 : m % 5 === 0 ? 7 : 4;
        ctx.moveTo(x, SCALE_H - len);
        ctx.lineTo(x, SCALE_H);
    }
    ctx.stroke();

    ctx.fillStyle = COLOR_MUTE;
    ctx.font = `11px ${FONT}`;
    ctx.textAlign = 'left';
    ctx.textBaseline = 'alphabetic';
    for (let m = Math.max(0, Math.ceil(m0 / 10) * 10); m <= m1; m += 10) {
        ctx.fillText(`${m / 10} cm`, (m - m0) * px + 3, 13);
    }

    ctx.strokeStyle = COLOR_LINE;
    ctx.beginPath();
    ctx.moveTo(0, SCALE_H + 0.5);
    ctx.lineTo(width, SCALE_H + 0.5);
    ctx.stroke();

    drawBand(
        ctx,
        width,
        m0,
        m1,
        px,
        ruler.bigStep,
        ruler.cungLon.length,
        (i) => ({ ten: ruler.cungLon[i].ten, cat: ruler.cungLon[i].catHung === 'cat' }),
        BIG_TOP,
        BIG_BOTTOM,
        `bold 13px ${FONT}`
    );
    drawBand(
        ctx,
        width,
        m0,
        m1,
        px,
        ruler.smallStep,
        ruler.flatMarks.length,
        (i) => ruler.flatMarks[i],
        BIG_BOTTOM,
        SMALL_BOTTOM,
        `600 12px ${FONT}`
    );

    const i0 = Math.max(0, Math.floor(m0 / ruler.smallStep));
    const i1 = Math.floor(m1 / ruler.smallStep);
    for (let i = i0; i <= i1; i++) {
        const idx = ((i % ruler.flatMarks.length) + ruler.flatMarks.length) % ruler.flatMarks.length;
        const mark = ruler.flatMarks[idx];
        ctx.fillStyle = mark.cat ? COLOR_CAT : COLOR_HUNG;
        ctx.fillRect((i * ruler.smallStep - m0) * px, SMALL_BOTTOM, ruler.smallStep * px + 0.6, STRIP_BOTTOM - SMALL_BOTTOM);
    }
}


const format = value => new Intl.NumberFormat('vi-VN', {maximumFractionDigits: 4}).format(value);
const interval = (start, end) => '(' + format(start) + '; ' + format(end) + '] mm';

export default function initLoBanRuler() {
    const root = document.querySelector('[data-loban-tool]');
    if (!root) return;
    const input = root.querySelector('[data-loban-input]'), unit = root.querySelector('[data-loban-unit]');
    const view = root.querySelector('[data-loban-view]'), error = root.querySelector('[data-loban-error]');
    const presetsEl = root.querySelector('[data-loban-presets]'), cardsEl = root.querySelector('[data-loban-cards]');
    const suggestEl = root.querySelector('[data-loban-suggest]'), label = root.querySelector('[data-loban-pointer-label]');
    const measurement = root.querySelector('[data-loban-measurement]'), summary = root.querySelector('[data-loban-summary]');
    const results = root.querySelector('[data-loban-results]');
    const canvases = preparedRulers.map(ruler => view.querySelector('canvas[data-loban-canvas="' + ruler.id + '"]'));
    let mm = parseMeasurement(input.value, unit.value) ?? 2120, off = 0, frame = 0, announceTimer;
    const px = 10; // Reference ruler: 10 CSS pixels/mm, identical at all viewport widths.
    const width = () => canvases[0].clientWidth;
    const syncInput = () => { input.value = String(unit.value === 'cm' ? mm / 10 : mm); };
    function render() {
        frame = 0;
        if (input.getAttribute('aria-invalid') === 'true') return;
        // Negative offset is intentional: zero stays under the central pointer.
        off = mm - width() / (2 * px);
        preparedRulers.forEach((ruler, index) => drawRuler(canvases[index], ruler, off, px));
        label.textContent = format(mm) + ' mm';
        measurement.textContent = format(mm) + ' mm = ' + format(mm / 10) + ' cm';
        view.setAttribute('aria-valuenow', String(mm));
        view.setAttribute('aria-valuetext', format(mm) + ' milimét');
        const verdicts = [];
        cardsEl.innerHTML = preparedRulers.map(ruler => {
            const found = findCung(ruler, mm / 10), {cungLon, cungBe, r} = found;
            const good = cungLon.catHung === 'cat';
            const warn = Math.min(r - cungBe.mmStart, cungBe.mmEnd - r) < EDGE_WARN_MM;
            verdicts.push(format(ruler.cycleMm / 10) + ' cm: ' + cungLon.ten + ', ' + cungBe.ten + ', ' + (good ? 'tốt' : 'xấu'));
            return '<article class="loban-card ' + (good ? 'is-cat' : 'is-hung') + '">' +
                '<h3>Thước ' + format(ruler.cycleMm / 10) + ' cm</h3>' +
                '<p class="loban-card-use">' + ruler.label.split('—')[1].trim() + '</p>' +
                '<span class="loban-verdict">' + (good ? 'Cung tốt' : 'Cung xấu') + '</span>' +
                '<div class="loban-cung">' + cungLon.ten + '</div><div class="loban-sub">' + cungBe.ten + '</div>' +
                '<dl class="loban-ranges"><dt>Khoảng cung lớn</dt><dd>' + interval(found.groupStart, found.groupEnd) + '</dd>' +
                '<dt>Khoảng cung nhỏ</dt><dd>' + interval(found.markStart, found.markEnd) + '</dd></dl>' +
                (warn ? '<p class="loban-warn">Sát ranh giới cung. Sai số đo hoặc thi công có thể đổi kết quả.</p>' : '') + '</article>';
        }).join('');
        const bands = findGoodBands(preparedRulers, mm);
        suggestEl.innerHTML = bands.length ? bands.map(band =>
            '<button type="button" class="loban-pill" data-mm="' + band.mid + '"><b>' + format(band.mid) +
            ' mm</b><small>' + format(band.mid / 10) + ' cm · dải ' + format(band.lo) + '–' + format(band.hi) + ' mm</small></button>'
        ).join('') : '<span class="loban-none">Không có dải phù hợp trong ±500 mm. Hãy ưu tiên thước đúng với hạng mục đang đo.</span>';
        clearTimeout(announceTimer);
        announceTimer = setTimeout(() => { summary.textContent = measurement.textContent + '. ' + verdicts.join('. '); }, 180);
    }
    function schedule() { if (!frame) frame = requestAnimationFrame(render); }
    function setMm(value) {
        mm = Math.max(0, Math.min(MAX_MM, Math.round(value)));
        input.removeAttribute('aria-invalid'); error.hidden = true; results.hidden = false;
        view.removeAttribute('aria-disabled');
        syncInput(); schedule();
    }
    input.addEventListener('input', () => {
        const value = parseMeasurement(input.value, unit.value);
        if (value === null) {
            input.setAttribute('aria-invalid', 'true'); error.hidden = false; results.hidden = true;
            view.setAttribute('aria-disabled', 'true');
            measurement.textContent = 'Chưa có số đo hợp lệ';
            clearTimeout(announceTimer); summary.textContent = ''; return;
        }
        // Preserve a decimal separator while the user is typing.
        mm = value; input.removeAttribute('aria-invalid'); error.hidden = true; results.hidden = false;
        view.removeAttribute('aria-disabled'); schedule();
    });
    unit.addEventListener('change', () => setMm(mm));
    root.querySelectorAll('[data-loban-step]').forEach(button => button.addEventListener('click', () => setMm(mm + Number(button.dataset.lobanStep))));
    presetsEl.innerHTML = PRESETS.map(p => '<button type="button" class="loban-chip" data-cm="' + p.cm + '">' + p.label + '</button>').join('');
    presetsEl.addEventListener('click', event => { const button = event.target.closest('[data-cm]'); if (button) setMm(Number(button.dataset.cm) * 10); });
    suggestEl.addEventListener('click', event => { const button = event.target.closest('[data-mm]'); if (button) setMm(Number(button.dataset.mm)); });
    view.addEventListener('keydown', event => {
        const moves = {ArrowLeft: -1, ArrowDown: -1, ArrowRight: 1, ArrowUp: 1, PageDown: -100, PageUp: 100};
        if (event.key === 'Home' || event.key === 'End') { event.preventDefault(); setMm(event.key === 'Home' ? 0 : MAX_MM); }
        else if (moves[event.key]) { event.preventDefault(); setMm(mm + moves[event.key] * (event.shiftKey ? 10 : 1)); }
    });
    let drag = null;
    view.addEventListener('pointerdown', event => {
        if (!event.isPrimary || event.button !== 0) return;
        drag = {id: event.pointerId, x: event.clientX, mm};
        view.setPointerCapture(event.pointerId); view.classList.add('is-dragging');
    });
    view.addEventListener('pointermove', event => {
        if (drag?.id === event.pointerId) setMm(drag.mm - (event.clientX - drag.x) / px);
    });
    function stopDrag() { drag = null; view.classList.remove('is-dragging'); }
    view.addEventListener('pointerup', stopDrag);
    view.addEventListener('pointercancel', stopDrag);
    view.addEventListener('lostpointercapture', stopDrag);
    // A vertical wheel must continue scrolling the page. Shift+wheel or trackpad horizontal pans the ruler.
    view.addEventListener('wheel', event => {
        const delta = Math.abs(event.deltaX) > Math.abs(event.deltaY) ? event.deltaX : event.shiftKey ? event.deltaY : 0;
        if (!delta) return;
        event.preventDefault(); setMm(mm + delta / px);
    }, {passive: false});
    document.querySelector('[data-loban-tables]').innerHTML = preparedRulers.map(ruler =>
        '<details><summary>Thước ' + format(ruler.cycleMm / 10) + ' cm · ' + ruler.cungLon.length + ' cung lớn / ' +
        ruler.flatMarks.length + ' cung nhỏ</summary><div class="loban-table-scroll" tabindex="0" role="region" aria-label="Bảng cung thước ' +
        format(ruler.cycleMm / 10) + ' cm"><table><caption>Khoảng số đo trong chu kỳ đầu (mm)</caption><thead><tr><th scope="col">Cung lớn</th>' +
        '<th scope="col">Cung nhỏ</th><th scope="col">Khoảng (mm)</th><th scope="col">Cát / hung</th></tr></thead><tbody>' +
        ruler.cungLon.map(group => group.cungBe.map(mark => '<tr class="' + (group.catHung === 'cat' ? 'is-cat' : 'is-hung') + '"><td>' +
        group.ten + '</td><td>' + mark.ten + '</td><td>' + interval(mark.mmStart, mark.mmEnd) + '</td><td>' +
        (group.catHung === 'cat' ? 'Tốt' : 'Xấu') + '</td></tr>').join('')).join('') + '</tbody></table></div></details>'
    ).join('');
    if (typeof ResizeObserver !== 'undefined') new ResizeObserver(schedule).observe(view);
    else window.addEventListener('resize', schedule);
    document.fonts?.ready.then(schedule);
    render();
}
