<?php

namespace App\Service;

/**
 * Tiện ích cho shortcode dạng khối trong nội dung CKEditor ([bai_viet],
 * [form_tai_checklist]…) — dùng chung cho ContentFormatter (hiển thị) và
 * controller (đọc lại tham số từ nội dung gốc, không tin dữ liệu trình duyệt gửi).
 */
final class Shortcode
{
    /**
     * [form_tai_checklist]                                   → báo sẽ gửi file qua Zalo
     * [form_tai_checklist file1="Tên nút|/uploads/a.pdf" …]  → hiện tối đa 5 nút tải
     * Tuỳ chọn: tieu_de="…", mo_ta="…".
     */
    public const CHECKLIST = 'form_tai_checklist';
    public const CHECKLIST_MAX_FILES = 5;

    private const SPACE = '(?:\s|&nbsp;|\x{00A0})*';
    private const INLINE_TAGS = 'strong|b|em|i|u|s|span|font|code|mark|small|sup|sub';

    public static function pattern(string $name): string
    {
        return '\[' . preg_quote($name, '#') . '\b[^\]]*\]';
    }

    /**
     * Thay mọi shortcode $name bằng HTML do $render trả về.
     *
     * CKEditor luôn đặt chữ gõ vào trong <p> — có khi kèm &nbsp;/<br>, bị bọc
     * thêm <strong>/<span>… hoặc nằm chung đoạn với chữ khác. Khối <div>/<form>
     * không được nằm trong <p>: trình duyệt tự đóng <p> sớm và để lại </p> thừa
     * làm vỡ bố cục. Nên: gỡ thẻ inline chỉ bọc riêng shortcode, tách đoạn <p>
     * quanh shortcode (giữ nguyên thuộc tính của <p>) và bỏ phần còn lại trống.
     *
     * @param callable(string): string $render nhận shortcode gốc
     */
    public static function replaceBlocks(string $content, string $name, callable $render): string
    {
        if (!str_contains($content, '[' . $name)) {
            return $content;
        }

        $shortcode = self::pattern($name);

        // Lặp vì thẻ inline có thể lồng nhiều lớp: <strong><span>[…]</span></strong>
        do {
            $content = preg_replace(
                '#<(' . self::INLINE_TAGS . ')\b[^>]*>' . self::SPACE . '(' . $shortcode . ')' . self::SPACE . '</\1>#u',
                '$2',
                $content,
                -1,
                $count
            );
        } while ($count > 0);

        $content = preg_replace_callback(
            '#<p\b([^>]*)>((?:(?!</p>).)*?' . $shortcode . '.*?)</p>#su',
            static function (array $m) use ($shortcode): string {
                $parts = preg_split('#(' . $shortcode . ')#u', $m[2], -1, PREG_SPLIT_DELIM_CAPTURE);
                $html = '';
                foreach ($parts as $i => $part) {
                    if ($i % 2 === 1) {
                        $html .= $part;
                        continue;
                    }
                    $part = preg_replace('#^(?:\s|&nbsp;|\x{00A0}|<br\s*/?>)+|(?:\s|&nbsp;|\x{00A0}|<br\s*/?>)+$#u', '', $part);
                    if (self::isBlank($part)) {
                        continue;
                    }
                    $html .= '<p' . $m[1] . '>' . $part . '</p>';
                }

                return $html;
            },
            $content
        );

        return preg_replace_callback('#' . $shortcode . '#u', static fn (array $m): string => $render($m[0]), $content);
    }

    /**
     * Tham số của shortcode. Dấu nháy có thể đã bị mã hoá (&quot;), là nháy
     * cong “…” (gõ từ Word) hoặc không có; khoảng trắng có thể là &nbsp;.
     *
     * @return array<string, string>
     */
    public static function attributes(string $shortcode): array
    {
        $shortcode = str_replace("\u{00A0}", ' ', html_entity_decode($shortcode, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        preg_match_all('/(\w+)\s*=\s*["\'“”]?([^"\'“”\]]*)/u', $shortcode, $pairs, PREG_SET_ORDER);

        $attributes = [];
        foreach ($pairs as $pair) {
            $attributes[strtolower($pair[1])] = trim($pair[2]);
        }

        return $attributes;
    }

    /**
     * Tham số của shortcode thứ $index (tính từ 0) có tên $name trong nội dung
     * gốc — cùng thứ tự với lúc hiển thị.
     *
     * @return array<string, string>|null
     */
    public static function find(string $content, string $name, int $index): ?array
    {
        if ($index < 0 || !preg_match_all('#' . self::pattern($name) . '#u', $content, $matches)) {
            return null;
        }

        return isset($matches[0][$index]) ? self::attributes($matches[0][$index]) : null;
    }

    /**
     * file1…file5 dạng "Tên nút|URL". Chỉ nhận đường dẫn nội bộ (/…) hoặc
     * http(s) — URL lạ (javascript:, data:…) bị bỏ qua.
     *
     * @param array<string, string> $attributes
     *
     * @return list<array{label: string, url: string}>
     */
    public static function checklistFiles(array $attributes): array
    {
        $files = [];
        for ($i = 1; $i <= self::CHECKLIST_MAX_FILES; ++$i) {
            $value = trim($attributes['file' . $i] ?? '');
            if ($value === '') {
                continue;
            }
            [$label, $url] = str_contains($value, '|') ? array_map('trim', explode('|', $value, 2)) : ['', $value];
            if (!preg_match('#^(?:/(?!/)|https?://)[^\s"\'<>]+$#i', $url)) {
                continue;
            }
            $files[] = ['label' => $label !== '' ? mb_substr($label, 0, 80) : 'Tải file ' . $i, 'url' => $url];
        }

        return $files;
    }

    private static function isBlank(string $html): bool
    {
        if (preg_match('#<(?:img|iframe|video|audio|svg|object|embed|input|hr)\b#i', $html)) {
            return false;
        }

        return trim(str_replace(["\u{00A0}", '&nbsp;'], '', strip_tags($html))) === '';
    }
}
