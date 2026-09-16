import { rulers } from './lo-ban-data.js';
import { findCung } from './lo-ban-calculator.js';

// Màu canvas không đọc được biến SCSS — giữ đồng bộ thủ công với
// public/assets/scss/v3/_tokens.scss ($primary, $text, $accent, $mut, $line).
const COLOR_CAT = '#b5462e'; // $primary — quy ước thước thật: đỏ = cung tốt
const COLOR_HUNG = '#2b2622'; // $text — đen/nâu than = cung xấu
const COLOR_TICK = '#c3b8ab';
const COLOR_LINE = '#e2d9cb';
const COLOR_MUTE = '#8a8078';
const FONT = '"Be Vietnam Pro", system-ui, sans-serif';

const MAX_MM = 20000;
const EDGE_WARN_MM = 2.5;
const SCALE_H = 28;
const BIG_TOP = 28;
const BIG_BOTTOM = 55;
const SMALL_BOTTOM = 88;
const STRIP_BOTTOM = 93;
const ROW_HEIGHT = 94;

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

function isAllCat(mm) {
    return preparedRulers.every((ruler) => findCung(ruler, mm / 10).cungLon.catHung === 'cat');
}

// Quét ±radius mm quanh 1 giá trị, tìm các dải liên tục mà cả 3 thước đều cát.
function findGoodBands(mm, radius) {
    const lo = Math.max(1, Math.round(mm - radius));
    const hi = Math.round(mm + radius);
    const out = [];
    let start = null;

    for (let v = lo; v <= hi + 1; v++) {
        const ok = v <= hi && isAllCat(v);
        if (ok && start === null) {
            start = v;
        } else if (!ok && start !== null) {
            out.push({ lo: start, hi: v - 1, mid: Math.round((start + v - 1) / 2) });
            start = null;
        }
    }

    return out
        .sort((a, b) => Math.abs(a.mid - mm) - Math.abs(b.mid - mm))
        .slice(0, 6)
        .sort((a, b) => a.mid - b.mid);
}

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

        const v0 = Math.max(xa, 2);
        const v1 = Math.min(xb, width - 2);
        if (v1 - v0 > 26) {
            ctx.fillStyle = mark.cat ? COLOR_CAT : COLOR_HUNG;
            let text = mark.ten;
            while (text.length > 3 && ctx.measureText(text).width > v1 - v0 - 4) {
                text = text.slice(0, -1);
            }
            ctx.fillText(text === mark.ten ? text : `${text}…`, (v0 + v1) / 2, (yTop + yBottom) / 2 + 1);
        }
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
    ctx.fillStyle = '#fff';
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

export default function initLoBanRuler() {
    const root = document.querySelector('[data-loban-tool]');
    if (!root) {
        return;
    }

    const input = root.querySelector('[data-loban-input]');
    const view = root.querySelector('[data-loban-view]');
    const presetsEl = root.querySelector('[data-loban-presets]');
    const cardsEl = root.querySelector('[data-loban-cards]');
    const suggestEl = root.querySelector('[data-loban-suggest]');
    const pointerLabelEl = root.querySelector('[data-loban-pointer-label]');
    if (!input || !view || !cardsEl) {
        return;
    }

    const canvases = {};
    preparedRulers.forEach((ruler) => {
        canvases[ruler.id] = view.querySelector(`canvas[data-loban-canvas="${ruler.id}"]`);
    });

    let px = 7;
    let off = 0;
    let mm = Math.max(0, Math.round((parseFloat(input.value) || 0) * 10));

    function fit() {
        px = view.clientWidth < 520 ? 6 : 7;
    }

    function drawAll() {
        preparedRulers.forEach((ruler) => {
            const canvas = canvases[ruler.id];
            if (canvas) {
                drawRuler(canvas, ruler, off, px);
            }
        });
    }

    function renderCards() {
        cardsEl.innerHTML = preparedRulers
            .map((ruler) => {
                const { cungLon, cungBe, r } = findCung(ruler, mm / 10);
                const isCat = cungLon.catHung === 'cat';
                const dist = Math.min(r - cungBe.mmStart, cungBe.mmEnd - r);
                const warn = dist < EDGE_WARN_MM;
                const useText = (ruler.label.split('—')[1] || '').trim();

                return `
                    <div class="loban-card ${isCat ? 'is-cat' : 'is-hung'}">
                        <h3>Thước ${(ruler.cycleMm / 10).toFixed(1).replace('.', ',')} cm</h3>
                        <p class="loban-card-use">${useText}</p>
                        <span class="loban-verdict">${isCat ? 'Cung tốt' : 'Cung xấu'}</span>
                        <div class="loban-cung">${cungLon.ten}${cungLon.alias ? ` (${cungLon.alias})` : ''}</div>
                        <div class="loban-sub">${cungBe.ten}</div>
                        ${warn ? '<div class="loban-warn">Sát mép cung — lệch vài milimet khi thi công có thể đổi kết quả, nên lùi vào giữa khoảng.</div>' : ''}
                    </div>`;
            })
            .join('');
    }

    function renderSuggestions() {
        if (!suggestEl) {
            return;
        }
        const bandsFound = findGoodBands(mm, 500);
        suggestEl.innerHTML = bandsFound.length
            ? bandsFound
                  .map(
                      (band) => `
                    <button type="button" class="loban-pill" data-mm="${band.mid}">
                        <b>${(band.mid / 10).toFixed(1).replace('.', ',')} cm</b>
                        <small>dải đẹp ${(band.lo / 10).toFixed(1)}–${(band.hi / 10).toFixed(1)} cm</small>
                    </button>`
                  )
                  .join('')
            : '<span class="loban-none">Không có dải nào trong khoảng ±50cm tốt trên cả ba thước. Hãy ưu tiên cây thước đúng với hạng mục đang đo.</span>';
    }

    function renderPointerLabel() {
        if (pointerLabelEl) {
            pointerLabelEl.textContent = `${(mm / 10).toFixed(1).replace('.', ',')} cm`;
        }
    }

    function render() {
        renderPointerLabel();
        renderCards();
        renderSuggestions();
    }

    function center() {
        off = Math.max(0, mm - view.clientWidth / 2 / px);
        drawAll();
    }

    function setMm(value, syncInput) {
        mm = Math.max(0, Math.min(MAX_MM, Math.round(value)));
        if (syncInput) {
            input.value = +(mm / 10).toFixed(1);
        }
        center();
        render();
    }

    function syncFromOffset() {
        mm = Math.max(0, Math.min(MAX_MM, Math.round(off + view.clientWidth / 2 / px)));
        input.value = +(mm / 10).toFixed(1);
        render();
    }

    input.addEventListener('input', () => {
        const cm = parseFloat(input.value.replace(',', '.'));
        if (Number.isFinite(cm) && cm >= 0) {
            setMm(cm * 10, false);
        }
    });

    if (presetsEl) {
        presetsEl.innerHTML = PRESETS.map((p) => `<button type="button" class="loban-chip" data-cm="${p.cm}">${p.label}</button>`).join('');
        presetsEl.addEventListener('click', (event) => {
            const chip = event.target.closest('.loban-chip');
            if (chip) {
                setMm(parseFloat(chip.dataset.cm) * 10, true);
            }
        });
    }

    if (suggestEl) {
        suggestEl.addEventListener('click', (event) => {
            const pill = event.target.closest('.loban-pill');
            if (pill) {
                setMm(parseFloat(pill.dataset.mm), true);
            }
        });
    }

    let dragging = false;
    let dragStartX = 0;
    let dragStartOff = 0;

    view.addEventListener('pointerdown', (event) => {
        dragging = true;
        dragStartX = event.clientX;
        dragStartOff = off;
        view.setPointerCapture(event.pointerId);
        view.classList.add('is-dragging');
    });
    view.addEventListener('pointermove', (event) => {
        if (!dragging) {
            return;
        }
        off = Math.max(0, Math.min(MAX_MM, dragStartOff - (event.clientX - dragStartX) / px));
        drawAll();
        syncFromOffset();
    });
    const stopDrag = () => {
        dragging = false;
        view.classList.remove('is-dragging');
    };
    view.addEventListener('pointerup', stopDrag);
    view.addEventListener('pointercancel', stopDrag);

    view.addEventListener(
        'wheel',
        (event) => {
            const delta = Math.abs(event.deltaX) > Math.abs(event.deltaY) ? event.deltaX : event.deltaY;
            if (!delta) {
                return;
            }
            event.preventDefault();
            off = Math.max(0, Math.min(MAX_MM, off + delta / px));
            drawAll();
            syncFromOffset();
        },
        { passive: false }
    );

    window.addEventListener('resize', () => {
        fit();
        center();
    });

    fit();
    center();
    render();
}
