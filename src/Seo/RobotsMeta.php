<?php

namespace App\Seo;

/**
 * Nội dung thẻ <meta name="robots"> dùng chung cho bài viết, trang, danh mục
 * và mặc định của layout.
 *
 * Trang được index thì kèm thêm chỉ thị xem trước: cho Google dùng ảnh lớn
 * (Discover, ảnh minh họa trong AI Overview), đoạn trích và video không giới
 * hạn độ dài. Trang noindex thì không cần.
 */
final class RobotsMeta
{
    public const PREVIEW = 'max-image-preview:large, max-snippet:-1, max-video-preview:-1';

    public const INDEX_FOLLOW = 'index, follow, ' . self::PREVIEW;

    public static function build(bool $index, bool $follow): string
    {
        $content = ($index ? 'index' : 'noindex') . ', ' . ($follow ? 'follow' : 'nofollow');

        return $index ? $content . ', ' . self::PREVIEW : $content;
    }
}
