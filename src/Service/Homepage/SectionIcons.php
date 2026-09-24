<?php

namespace App\Service\Homepage;

/**
 * Bộ icon dùng cho khối "Cam kết" trên trang chủ.
 *
 * Icon là phần bên trong thẻ <svg> và được template in ra bằng |raw, nên
 * KHÔNG cho admin nhập markup tự do — admin chỉ chọn trong danh sách này.
 * Muốn thêm icon thì thêm case ở đây (markup lấy từ bộ Lucide, stroke 2px,
 * viewBox 24x24 cho khớp theme v3).
 */
final class SectionIcons
{
    public const DEFAULT = 'check';

    /**
     * key => [nhãn hiển thị trong admin, markup bên trong <svg>]
     */
    private const ICONS = [
        'clock' => ['Đồng hồ — tiến độ', '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>'],
        'money' => ['Chi phí — ngân sách', '<path d="M12 1v22M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>'],
        'materials' => ['Thùng hàng — vật tư', '<rect width="20" height="20" x="2" y="2" rx="3"/><path d="M20 7h-9m9 10h-9M5 7h.01M5 17h.01"/>'],
        'supervisor' => ['Người — giám sát', '<path d="M2 21a8 8 0 0 1 13.29-6"/><circle cx="10" cy="8" r="5"/><path d="m16 19 2 2 4-4"/>'],
        'shield' => ['Khiên — bảo hành', '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/>'],
        'contract' => ['Văn bản — hợp đồng', '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M16 13H8M16 17H8M10 9H8"/>'],
        'check' => ['Dấu tích', '<path d="M20 6 9 17l-5-5"/>'],
        'home' => ['Ngôi nhà', '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/><path d="M9 21v-6h6v6"/>'],
        'ruler' => ['Thước — thiết kế', '<path d="M3 15 15 3l6 6L9 21z"/><path d="m7.5 10.5 2 2M10.5 7.5l2 2M13.5 4.5l2 2"/>'],
        'phone' => ['Điện thoại — hỗ trợ', '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>'],
    ];

    /**
     * Markup bên trong <svg>. Key lạ trả về icon mặc định để khối cam kết
     * không bao giờ mất hình.
     */
    public static function markup(?string $key): string
    {
        return self::ICONS[$key][1] ?? self::ICONS[self::DEFAULT][1];
    }

    /**
     * @return array<string, string> nhãn => key, dùng cho ChoiceType
     */
    public static function choices(): array
    {
        $choices = [];

        foreach (self::ICONS as $key => [$label]) {
            $choices[$label] = $key;
        }

        return $choices;
    }

    public static function has(?string $key): bool
    {
        return $key !== null && isset(self::ICONS[$key]);
    }

    /**
     * Tìm key theo markup — dùng khi nhập nội dung cũ từ config/packages/v3.yaml
     * (chỗ đó lưu thẳng markup SVG) vào DB.
     */
    public static function keyForMarkup(?string $markup): string
    {
        $needle = preg_replace('/\s+/', '', (string) $markup);

        foreach (self::ICONS as $key => [, $candidate]) {
            if (preg_replace('/\s+/', '', $candidate) === $needle) {
                return $key;
            }
        }

        return self::DEFAULT;
    }
}
