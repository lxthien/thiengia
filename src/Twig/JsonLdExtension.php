<?php

namespace App\Twig;

use App\Service\JsonLdNormalizer;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class JsonLdExtension extends AbstractExtension
{
    /**
     * Giữ nguyên tiếng Việt và dấu "/" cho dễ đọc khi xem nguồn trang. Chỉ cần
     * mã hóa < > (JSON_HEX_TAG) để chuỗi trong dữ liệu không thể đóng thẻ
     * <script> — trong <script> trình duyệt không giải mã &, ', " nên không
     * cần HEX_AMP/APOS/QUOT.
     */
    public const FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG
        | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR;

    public function __construct(private readonly JsonLdNormalizer $normalizer)
    {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('safe_json_ld', [$this, 'normalize'], ['is_safe' => ['html']]),
            // Giá trị/mảng Twig → JSON để in trong <script type="application/ld+json">.
            new TwigFilter('json_ld', [$this, 'encode'], ['is_safe' => ['html', 'js']]),
        ];
    }

    public function normalize(?string $value): string
    {
        return $this->normalizer->normalize($value) ?? '';
    }

    public function encode(mixed $value, bool $pretty = false): string
    {
        return json_encode($value, self::FLAGS | ($pretty ? JSON_PRETTY_PRINT : 0));
    }
}
