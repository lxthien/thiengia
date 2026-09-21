<?php

namespace App\Service;

final class GalleryImageInput
{
    public function metadata(array $data): array
    {
        $result = [];
        foreach (['caption', 'alt'] as $field) {
            $value = $data[$field] ?? '';
            if (!is_string($value) || mb_strlen($value) > 255) {
                throw new \InvalidArgumentException('Chú thích và alt phải là văn bản, tối đa 255 ký tự.');
            }
            $result[$field] = trim($value);
        }
        return $result;
    }

    public function imageUrl(mixed $value, string $publicDirectory): string
    {
        if (!is_string($value) || mb_strlen($value) > 500 || !str_starts_with($value, '/uploads/media/')) {
            throw new \InvalidArgumentException('Vui lòng chọn ảnh từ thư viện media.');
        }
        $decoded = rawurldecode($value);
        if (preg_match('~[\\\\\x00-\x1f?#]~', $decoded) || str_contains($decoded, '/../') || str_contains($decoded, '/./')) {
            throw new \InvalidArgumentException('Đường dẫn ảnh không hợp lệ.');
        }
        $root = realpath($publicDirectory . '/uploads/media');
        $file = realpath($publicDirectory . $decoded);
        $prefix = $root === false ? '' : str_replace('\\', '/', $root) . '/';
        if (!$root || !$file || !is_file($file) || !str_starts_with(str_replace('\\', '/', $file), $prefix)) {
            throw new \InvalidArgumentException('Ảnh không tồn tại trong thư viện.');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file);
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/avif'], true)) {
            throw new \InvalidArgumentException('Chỉ chọn ảnh JPEG, PNG, WebP, GIF hoặc AVIF.');
        }
        return $value;
    }
}
