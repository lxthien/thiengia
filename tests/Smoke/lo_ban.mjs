import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
const moduleFrom = async path => import('data:text/javascript;base64,' + Buffer.from(readFileSync(new URL(path, import.meta.url), 'utf8')).toString('base64'));
const {rulers} = await moduleFrom('../../public/assets/js/v3/modules/lo-ban-data.js');
const {findCung, parseMeasurement, findGoodBands, MAX_MM} = await moduleFrom('../../public/assets/js/v3/modules/lo-ban-calculator.js');
// Independent names/color fixture from the reference's interactive dataset, checked 2026-09-20.
const reference = {"lcung":[["QUÝ NHÂN","HIỂM HỌA","THIÊN TAI","THIÊN TÀI","NHÂN LỘC","CÔ ĐỘC","THIÊN TẶC","TỂ TƯỚNG"],["Tài","Bệnh","Ly","Nghĩa","Quan","Kiếp","Hại","Bản"],["Đinh","Hại","Vượng","Khổ","Nghĩa","Quan","Tử","Hưng","Thất","Tài"]],"lkhoang":[["Quyền Lộc","Trung Tín","Tác Quan","Phát Đạt","Thông Minh","Án Thành","Hỗn Nhân","Thất Hiếu","Tai Họa","Trường Bệnh","Hoàn Tử","Quan Tài","Thân Tàn","Thất Tài","Hệ Quả","Thi Thơ","Văn Học","Thanh Quý","Tác Lộc","Thiên Lộc","Trí Tồn","Phú Quý","Tiến Bửu","Thập Thiện","Văn Chương","Bạc Nghịch","Vô Vọng","Ly Tán","Tửu Thục","Dâm Dục","Phong Bệnh","Chiêu Ôn","Ồn Tài","Ngục Tù","Quang Tài","Đại Tài","Thi Thơ","Hoạch Tài","Hiếu Tử","Quý Nhân"],["Tài Đức","Bảo Khố","Lục Hợp","Nghênh Phúc","Thoái Tài","Công Sự","Lao Chấp","Cô Quả","Trường Bệnh","Kiếp Tài","Quan Quỉ","Thất Thoát","Thêm Đinh","Ích Lợi","Quí Tử","Đại Cát","Thuận Khoa","Hoạch Tài","Tấn Đức","Phú Quí","Tử Biệt","Khoái Khẩu","Ly Hương","Thất Tài","Tai Chí","Tử Tuyệt","Lâm Bệnh","Khẩu Thiệt","Tài Chí","Đăng Khoa","Tiến Bảo","Hưng Vượng"],["Phúc Tinh","Cấp Đệ","Tài Vượng","Đăng Khoa","Khẩu Thiệt","Lâm Bệnh","Tử Tuyệt (Tử Huyệt)","Tai Chí","Thiên Đức","Hỷ Sự","Tiến Bảo","Nạp Phúc","Thất Thoát","Quan Quỉ","Kiếp Tài","Vô Tự","Đại Cát","Tài Vượng","Ích Lợi","Thiên Khố","Phú Quí","Tiến Bảo","Hoạch Tài","Thuận Khoa","Ly Hương","Tử Biệt","Thoái Đinh","Thất Tài","Đông Khoa","Quí Tử","Thêm Đinh","Hưng Vượng","Cô Quả","Lao Chấp","Công Sự","Thoái Tài","Nghênh Phúc","Lục Hợp","Tiến Bảo","Tài Đức"]],"lmau":[["#ff0000","#000000","#000000","#ff0000","#ff0000","#000000","#000000","#ff0000"],["#ff0000","#000000","#000000","#ff0000","#ff0000","#000000","#000000","#ff0000"],["#ff0000","#000000","#ff0000","#000000","#ff0000","#ff0000","#000000","#ff0000","#000000","#ff0000"]]};
for (const [i,ruler] of rulers.entries()) {
    const marks=ruler.cungLon.flatMap(g=>g.cungBe), size=ruler.cycleMm/marks.length;
    assert.deepEqual(ruler.cungLon.map(g=>g.ten.toUpperCase()),reference.lcung[i].map(s=>s.toUpperCase()));
    assert.deepEqual(marks.map(m=>m.ten),reference.lkhoang[i]);
    assert.deepEqual(ruler.cungLon.map(g=>g.catHung==='cat'?'#ff0000':'#000000'),reference.lmau[i]);
    for(let mm=0;mm<=MAX_MM;mm++){
        // Algebraically equivalent source formula without 291/9.7 rounding to 30.000000000000004.
        const r=mm%ruler.cycleMm, large=r===0?0:Math.ceil(r*ruler.cungLon.length/ruler.cycleMm)-1;
        const small=r===0?0:Math.ceil(r*marks.length/ruler.cycleMm)-1, found=findCung(ruler,mm/10);
        assert.equal(found.cungLon.ten.toUpperCase(),reference.lcung[i][large].toUpperCase(),ruler.id+' group '+mm);
        assert.equal(found.cungBe.ten,reference.lkhoang[i][small],ruler.id+' mark '+mm);
    }
    for (let cycle=0;cycle<20;cycle++) for(let k=1;k<=marks.length;k++){
        const mm=cycle*ruler.cycleMm+k*size, at=findCung(ruler,mm/10);
        assert.equal(at.cungBe.ten,marks[k===marks.length?0:k-1].ten,'exact boundary '+mm);
        assert.equal(findCung(ruler,(mm-.001)/10).cungBe.ten,marks[k-1].ten,'before boundary');
        assert.equal(findCung(ruler,(mm+.001)/10).cungBe.ten,marks[k%marks.length].ten,'after boundary');
        assert.ok(at.markEnd>at.markStart);
    }
}
assert.equal(parseMeasurement('2120','mm'),2120);
assert.equal(parseMeasurement('212,1','cm'),2121);
assert.equal(parseMeasurement('5000','cm'),MAX_MM);
assert.equal(parseMeasurement('0','mm'),0);
for(const value of ['', '-1','50001','1e3','12abc','Infinity','.1','1,2,3']) assert.equal(parseMeasurement(value,'mm'),null,value);
assert.equal(parseMeasurement('1.23','cm'),null);
for(const bad of [-1,NaN,Infinity]) assert.throws(()=>findCung(rulers[0],bad),RangeError);
for(const mm of [0,68,810,2120,49999,50000]) for(const band of findGoodBands(rulers,mm)){
    assert.ok(band.lo>=1 && band.hi<=MAX_MM);
    assert.ok(band.mid>=band.lo && band.mid<=band.hi);
    for(let value=band.lo;value<=band.hi;value++) assert.ok(rulers.every(r=>findCung(r,value/10).cungLon.catHung==='cat'));
}
console.log('PASS: 150,003 reference comparisons, all labels/colors, 20 cycles of boundaries, units/input validation and bounded suggestions.');
