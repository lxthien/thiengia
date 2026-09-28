<?php

namespace App\Service;

/**
 * Chuẩn hoá URL của mục menu (liên kết tùy chỉnh do admin nhập).
 *
 * Website in thẳng URL vào href (templates/v3/layout/_header.html.twig): Twig
 * escape ký tự HTML nhưng KHÔNG chặn giao thức — "javascript:..." vẫn chạy khi
 * bấm. Vì vậy chỉ nhận http(s), mailto:, tel:, "#điểm-neo" và đường dẫn nội bộ.
 */
final class MenuUrlNormalizer
{
    /**
     * @return string|null URL đã chuẩn hoá, hoặc null nếu không hợp lệ
     */
    public static function normalize(?string $url): ?string
    {
        $url = trim((string) $url);

        if ($url === '' || $url === '#') {
            return '#';
        }

        // Ký tự điều khiển/khoảng trắng/backslash: dấu hiệu URL bị làm rối (vd "java\tscript:").
        if (preg_match('/[\x00-\x20\x7f\\\\]/', $url)) {
            return null;
        }

        if (preg_match('#^[a-z][a-z\d+.\-]*:#i', $url)) {
            return preg_match('#^(https?://[^/]|mailto:|tel:)#i', $url) ? $url : null;
        }

        // "//host/..." là URL tuyệt đối sang domain khác — yêu cầu ghi rõ https://.
        if (str_starts_with($url, '//')) {
            return null;
        }

        if ($url[0] === '#' || $url[0] === '?') {
            return $url;
        }

        // Đường dẫn nội bộ: luôn có "/" đầu; thêm "/" cuối (quy ước URL của site)
        // chỉ khi không có query/điểm neo và không phải file (vd /sitemap.xml).
        $path = '/' . ltrim($url, '/');
        if (!preg_match('/[?#]/', $path) && !preg_match('#\.[a-z0-9]{2,5}$#i', $path)) {
            $path = rtrim($path, '/') . '/';
        }

        return $path;
    }

    public static function isSafe(?string $url): bool
    {
        return self::normalize($url) !== null;
    }
}
