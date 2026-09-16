// Dữ liệu 3 loại Thước Lỗ Ban — đối chiếu chéo giữa mã nguồn JS của công cụ
// tính toán thật (vietnamarch.com.vn) và bảng HTML độc lập (akisa.vn), khớp
// nhau 100% ở số lượng cung/mm mỗi mức/cát-hung. 2 điểm khác nhau giữa các
// nguồn đã được chốt: thước 38,8cm dùng mô hình 10 cung (không phải bản
// 8-cung của topvis.vn); thước 42,9cm dùng tên "Kiếp"/"Bản" (không phải
// "Nạn"/"Mạng" — 2 tên đó lưu lại ở field `alias` để tham khảo).
//
// mmStart/mmEnd tính TOÁN từ chu kỳ + số cung, không hardcode số thập phân
// tay — tránh sai số/lỗi gõ khi tự chia. Quy tắc biên: mỗi mức chiếm khoảng
// (mmStart, mmEnd] (mở đầu, đóng cuối).
function buildRuler({ label, cycleMm, cungBeCountPerCungLon, cungLonDefs }) {
    const cungLonWidth = cycleMm / cungLonDefs.length;
    const cungBeWidth = cungLonWidth / cungBeCountPerCungLon;

    let cursor = 0;
    const cungLon = cungLonDefs.map((def) => {
        const mmStart = cursor;
        const mmEnd = cursor + cungLonWidth;
        cursor = mmEnd;

        let subCursor = mmStart;
        const cungBe = def.mucList.map((ten) => {
            const subStart = subCursor;
            const subEnd = subCursor + cungBeWidth;
            subCursor = subEnd;
            return { ten, mmStart: subStart, mmEnd: subEnd };
        });

        return {
            ten: def.ten,
            alias: def.alias || null,
            catHung: def.catHung,
            mmStart,
            mmEnd,
            cungBe,
        };
    });

    return { label, cycleMm, cungLon };
}

export const ruler522 = buildRuler({
    label: 'Thước 52,2cm — Thông thủy (khoảng thông thủy cửa, cửa sổ, cổng)',
    cycleMm: 522,
    cungBeCountPerCungLon: 5,
    cungLonDefs: [
        { ten: 'Quý Nhân', catHung: 'cat', mucList: ['Quyền Lộc', 'Trung Tín', 'Tác Quan', 'Phát Đạt', 'Thông Minh'] },
        { ten: 'Hiểm Họa', catHung: 'hung', mucList: ['Án Thành', 'Hỗn Nhân', 'Thất Hiếu', 'Tai Họa', 'Thường Bệnh'] },
        { ten: 'Thiên Tai', catHung: 'hung', mucList: ['Hoàn Tử', 'Quan Tài', 'Thân Tàn', 'Thất Tài', 'Hệ Quả'] },
        { ten: 'Thiên Tài', catHung: 'cat', mucList: ['Thi Thơ', 'Văn Học', 'Thanh Quý', 'Tác Lộc', 'Thiên Lộc'] },
        { ten: 'Nhân Lộc', catHung: 'cat', mucList: ['Trí Tồn', 'Phú Quý', 'Tiến Bửu', 'Thập Thiện', 'Văn Chương'], alias: 'Phúc Lộc' },
        { ten: 'Cô Độc', catHung: 'hung', mucList: ['Bạc Nghịch', 'Vô Vọng', 'Ly Tán', 'Tửu Thục', 'Dâm Dục'] },
        { ten: 'Thiên Tặc', catHung: 'hung', mucList: ['Phong Bệnh', 'Chiêu Ôn', 'Ôn Tài', 'Ngục Tù', 'Quang Tài'] },
        { ten: 'Tể Tướng', catHung: 'cat', mucList: ['Đại Tài', 'Thi Thơ', 'Hoạch Tài', 'Hiếu Tử', 'Quý Nhân'] },
    ],
});

export const ruler429 = buildRuler({
    label: 'Thước 42,9cm — Khối xây dựng (bậc thềm, bệ bếp, tủ, kệ...)',
    cycleMm: 429,
    cungBeCountPerCungLon: 4,
    cungLonDefs: [
        { ten: 'Tài', catHung: 'cat', mucList: ['Tài Đức', 'Bảo Khố', 'Lục Hợp', 'Nghênh Phúc'] },
        { ten: 'Bệnh', catHung: 'hung', mucList: ['Thoái Tài', 'Công Sự', 'Lao Chấp', 'Cô Quả'] },
        { ten: 'Ly', catHung: 'hung', mucList: ['Trường Bệnh', 'Kiếp Tài', 'Quan Quỉ', 'Thất Thoát'] },
        { ten: 'Nghĩa', catHung: 'cat', mucList: ['Thêm Đinh', 'Ích Lợi', 'Quí Tử', 'Đại Cát'] },
        { ten: 'Quan', catHung: 'cat', mucList: ['Thuận Khoa', 'Hoạch Tài', 'Tấn Đức', 'Phú Quí'] },
        { ten: 'Kiếp', catHung: 'hung', mucList: ['Tử Biệt', 'Thoái Khẩu', 'Ly Hương', 'Thất Tài'], alias: 'Nạn' },
        { ten: 'Hại', catHung: 'hung', mucList: ['Tai Chí', 'Tử Tuyệt', 'Lâm Bệnh', 'Khẩu Thiệt'] },
        { ten: 'Bản', catHung: 'cat', mucList: ['Tài Chí', 'Đăng Khoa', 'Tiến Bảo', 'Hưng Vượng'], alias: 'Mạng' },
    ],
});

export const ruler388 = buildRuler({
    label: 'Thước 38,8cm — Âm phần (bàn thờ, tủ thờ, đồ nội thất tâm linh)',
    cycleMm: 388,
    cungBeCountPerCungLon: 4,
    cungLonDefs: [
        { ten: 'Đinh', catHung: 'cat', mucList: ['Phúc Tinh', 'Cấp Đệ', 'Tài Vượng', 'Đăng Khoa'] },
        { ten: 'Hại', catHung: 'hung', mucList: ['Khẩu Thiệt', 'Lâm Bệnh', 'Tử Tuyệt', 'Tai Chí'] },
        { ten: 'Vượng', catHung: 'cat', mucList: ['Thiên Đức', 'Hỷ Sự', 'Tiến Bảo', 'Nạp Phúc'] },
        { ten: 'Khổ', catHung: 'hung', mucList: ['Thất Thoát', 'Quan Quỉ', 'Kiếp Tài', 'Vô Tự'] },
        { ten: 'Nghĩa', catHung: 'cat', mucList: ['Đại Cát', 'Tài Vượng', 'Ích Lợi', 'Thiên Khố'] },
        { ten: 'Quan', catHung: 'cat', mucList: ['Phú Quí', 'Tiến Bảo', 'Hoạch Tài', 'Thuận Khoa'] },
        { ten: 'Tử', catHung: 'hung', mucList: ['Ly Hương', 'Tử Biệt', 'Thoái Đinh', 'Thất Tài'] },
        { ten: 'Hưng', catHung: 'cat', mucList: ['Đăng Khoa', 'Quí Tử', 'Thêm Đinh', 'Hưng Vượng'] },
        { ten: 'Thất', catHung: 'hung', mucList: ['Cô Quả', 'Lao Chấp', 'Công Sự', 'Thoái Tài'] },
        { ten: 'Tài', catHung: 'cat', mucList: ['Nghênh Phúc', 'Lục Hợp', 'Tiến Bảo', 'Tài Đức'] },
    ],
});

export const rulers = [
    { id: '522', ...ruler522 },
    { id: '429', ...ruler429 },
    { id: '388', ...ruler388 },
];
