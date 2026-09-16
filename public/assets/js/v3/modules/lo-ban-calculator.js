const EPSILON = 1e-6;

// Quy tắc biên: mỗi mức chiếm (mmStart, mmEnd] — riêng r=0 (đúng bội số chu
// kỳ, kể cả cm=0) được xếp vào mức ĐẦU TIÊN (k=1), không phải mức cuối.
function findMark(list, r) {
    if (r <= EPSILON) {
        return list[0];
    }
    return (
        list.find((item) => r > item.mmStart + EPSILON && r <= item.mmEnd + EPSILON) ||
        list[list.length - 1]
    );
}

export function findCung(ruler, cm) {
    const mm = cm * 10;
    let r = mm % ruler.cycleMm;
    if (r < 0) {
        r += ruler.cycleMm;
    }

    const cungLon = findMark(ruler.cungLon, r);
    const cungBe = findMark(cungLon.cungBe, r);

    return { mm, r, cungLon, cungBe };
}
