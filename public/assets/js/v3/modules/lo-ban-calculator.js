const EPSILON = 1e-7;
export const MAX_MM = 50000;

// Source convention: (start, end], with exact cycle multiples at the first mark.
export function findCung(ruler, cm) {
    const mm = cm * 10;
    if (!Number.isFinite(mm) || mm < 0) throw new RangeError('Kích thước phải là số không âm.');
    let r = mm % ruler.cycleMm;
    if (r < EPSILON || ruler.cycleMm - r < EPSILON) r = 0;
    const smallCount = ruler.cungLon.reduce((sum, group) => sum + group.cungBe.length, 0);
    const index = r === 0 ? 0 : Math.max(0, Math.ceil((r - EPSILON) / (ruler.cycleMm / smallCount)) - 1);
    const perGroup = ruler.cungLon[0].cungBe.length;
    const cungLon = ruler.cungLon[Math.floor(index / perGroup)];
    const cungBe = cungLon.cungBe[index % perGroup];
    const base = mm - r;
    return { mm, r, cungLon, cungBe,
        groupStart: base + cungLon.mmStart, groupEnd: base + cungLon.mmEnd,
        markStart: base + cungBe.mmStart, markEnd: base + cungBe.mmEnd };
}

export function parseMeasurement(value, unit) {
    const text = String(value).trim().replace(',', '.');
    if (!/^(?:\d+(?:\.\d*)?|\.\d+)$/.test(text)) return null;
    const mm = Number(text) * (unit === 'cm' ? 10 : 1);
    if (!Number.isFinite(mm) || mm < 0 || mm > MAX_MM || Math.abs(mm - Math.round(mm)) > EPSILON) return null;
    return Math.round(mm);
}

export function findGoodBands(rulers, mm, radius = 500) {
    const lo = Math.max(1, Math.round(mm - radius)), hi = Math.min(MAX_MM, Math.round(mm + radius));
    const out = [];
    let start = null;
    for (let value = lo; value <= hi + 1; value++) {
        const good = value <= hi && rulers.every(ruler => findCung(ruler, value / 10).cungLon.catHung === 'cat');
        if (good && start === null) start = value;
        else if (!good && start !== null) {
            out.push({lo: start, hi: value - 1, mid: Math.round((start + value - 1) / 2)}); start = null;
        }
    }
    return out.sort((a, b) => Math.abs(a.mid - mm) - Math.abs(b.mid - mm)).slice(0, 6).sort((a, b) => a.mid - b.mid);
}
