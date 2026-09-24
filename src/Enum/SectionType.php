<?php

namespace App\Enum;

/**
 * Danh sách khối (section) trên trang chủ.
 *
 * Đây là tập CỐ ĐỊNH do dev định nghĩa — mỗi case tương ứng 1 file
 * templates/homepage/sections/_<value>.html.twig. Admin được sắp thứ tự,
 * bật/tắt và sửa nội dung, nhưng không thêm/xóa khối để layout và CSS
 * của theme v3 luôn toàn vẹn.
 */
enum SectionType: string
{
    case Hero = 'hero';
    case Figures = 'figures';
    case Commitments = 'commitments';
    case About = 'about';
    case Services = 'services';
    case Works = 'works';
    case Testimonials = 'testimonials';
    case Steps = 'steps';
    case News = 'news';
    case Cta = 'cta';
    case Gallery = 'gallery';

    public function label(): string
    {
        return match ($this) {
            self::Hero => 'Hero + form báo giá',
            self::Figures => 'Dải số liệu',
            self::Commitments => 'Cam kết',
            self::About => 'Giới thiệu',
            self::Services => 'Dịch vụ',
            self::Works => 'Công trình thực tế',
            self::Testimonials => 'Khách hàng đánh giá',
            self::Steps => 'Quy trình làm việc',
            self::News => 'Cẩm nang (bài viết)',
            self::Cta => 'CTA cuối trang',
            self::Gallery => 'Hình ảnh hoạt động',
        };
    }

    /**
     * Nguồn dữ liệu của khối — hiện ngay trong admin để người nhập liệu
     * biết phải sửa nội dung ở đâu.
     */
    public function hint(): string
    {
        return match ($this) {
            self::Hero => 'Ảnh nền lấy từ Banner (nhóm hero). Gạch đầu dòng lấy từ cấu hình theme.',
            self::Figures => 'Bốn con số lấy từ cấu hình theme.',
            self::Commitments => 'Danh sách cam kết lấy từ cấu hình theme.',
            self::About => 'Ảnh và nội dung giới thiệu lấy từ cấu hình theme.',
            self::Services => 'Danh sách dịch vụ lấy từ cấu hình theme.',
            self::Works => 'Danh sách công trình lấy từ cấu hình theme.',
            self::Testimonials => 'Nội dung lấy từ mục Đánh giá khách hàng.',
            self::Steps => 'Tám bước lấy từ cấu hình theme.',
            self::News => 'Bài viết lấy theo cấu hình "Danh mục hiển thị trang chủ" trong Cài đặt chung.',
            self::Cta => 'Ảnh nền lấy từ cấu hình theme.',
            self::Gallery => 'Nội dung lấy từ mục Thư viện ảnh.',
        };
    }

    /**
     * Những ô chữ khối này thực sự dùng — form admin chỉ hiện đúng các ô đó
     * để không ai nhập vào chỗ không hiển thị ra đâu cả.
     *
     * @return list<'label'|'title'|'subtitle'>
     */
    public function editableFields(): array
    {
        return match ($this) {
            self::Figures => [],
            self::Hero, self::About => ['label', 'title', 'subtitle'],
            self::Cta => ['title', 'subtitle'],
            self::Testimonials => ['label'],
            default => ['label', 'title'],
        };
    }

    public function hasField(string $field): bool
    {
        return in_array($field, $this->editableFields(), true);
    }

    /**
     * Nhãn của ô "subtitle" đổi theo ngữ cảnh từng khối.
     */
    public function subtitleLabel(): string
    {
        return match ($this) {
            self::Cta => 'Ghi chú dưới tiêu đề',
            self::Hero => 'Đoạn giới thiệu ngắn',
            default => 'Mô tả',
        };
    }

    /**
     * Thứ tự mặc định — đúng thứ tự trang chủ đang chạy.
     *
     * @return list<self>
     */
    public static function defaultOrder(): array
    {
        return [
            self::Hero,
            self::Figures,
            self::Commitments,
            self::About,
            self::Services,
            self::Works,
            self::Testimonials,
            self::Steps,
            self::News,
            self::Cta,
            self::Gallery,
        ];
    }
}
