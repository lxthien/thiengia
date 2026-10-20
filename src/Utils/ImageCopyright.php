<?php

namespace App\Utils;

/**
 * Ghi thông tin bản quyền (XMP: Creator, Copyright Notice, Credit Line — các
 * trường Google Images hiển thị) vào ảnh JPEG/PNG/WebP.
 *
 * File này cố tình không phụ thuộc gì (không autoload, không Symfony): connector
 * CKFinder require thẳng nó (xem plugins/ImageCopyright trong cksourceckfinder),
 * mà connector đó không được dùng autoloader của app — xem ghi chú ở config.php.
 *
 * Mọi bước resize/đổi định dạng bằng GD (CKFinder maxWidth, WebP của
 * ContentFormatter, thumbnail LiipImagine) đều xoá sạch metadata, nên phải ghi
 * lại ở từng file đầu ra chứ không chỉ ở ảnh gốc.
 */
final class ImageCopyright
{
    // Giữ khớp company.* trong config/packages/v3.yaml (CKFinder không đọc được YAML).
    public const CREATOR = 'Xây Dựng Thiện Gia';
    public const CREDIT = 'Xây Dựng Thiện Gia – xaydungthiengia.com';
    public const RIGHTS = '© CÔNG TY TNHH XÂY DỰNG THIỆN GIA – xaydungthiengia.com';

    private const XMP_NS_JPEG = "http://ns.adobe.com/xap/1.0/\0";
    private const XMP_EXT_JPEG = "http://ns.adobe.com/xmp/extension/\0";
    private const XMP_KEYWORD_PNG = 'XML:com.adobe.xmp';

    /**
     * Gói XMP đầy đủ. Cũng dùng làm dấu nhận biết: file đã chứa đúng chuỗi này
     * thì bỏ qua, đổi nội dung hằng số ở trên thì lần chạy sau ghi đè bản cũ.
     */
    public static function xmpPacket(): string
    {
        $e = static fn (string $v): string => htmlspecialchars($v, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        return '<?xpacket begin="' . "\u{FEFF}" . '" id="W5M0MpCehiHzreSzNTczkc9d"?>'
            . '<x:xmpmeta xmlns:x="adobe:ns:meta/"><rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
            . '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/"'
            . ' xmlns:photoshop="http://ns.adobe.com/photoshop/1.0/" xmlns:xmpRights="http://ns.adobe.com/xap/1.0/rights/"'
            . ' photoshop:Credit="' . $e(self::CREDIT) . '" xmpRights:Marked="True">'
            . '<dc:creator><rdf:Seq><rdf:li>' . $e(self::CREATOR) . '</rdf:li></rdf:Seq></dc:creator>'
            . '<dc:rights><rdf:Alt><rdf:li xml:lang="x-default">' . $e(self::RIGHTS) . '</rdf:li></rdf:Alt></dc:rights>'
            . '</rdf:Description></rdf:RDF></x:xmpmeta><?xpacket end="r"?>';
    }

    public static function hasCopyright(string $data): bool
    {
        return str_contains($data, self::xmpPacket());
    }

    /**
     * @return string|null nội dung ảnh đã ghi XMP; null nếu đã có sẵn, không phải
     *                     JPEG/PNG/WebP hoặc cấu trúc file không đọc được (để nguyên).
     */
    public static function apply(string $data): ?string
    {
        if (self::hasCopyright($data)) {
            return null;
        }

        try {
            if (str_starts_with($data, "\xFF\xD8")) {
                return self::applyJpeg($data);
            }
            if (str_starts_with($data, "\x89PNG\r\n\x1A\n")) {
                return self::applyPng($data);
            }
            if (str_starts_with($data, 'RIFF') && substr($data, 8, 4) === 'WEBP') {
                return self::applyWebp($data);
            }
        } catch (\UnexpectedValueException) {
            // File hỏng/cấu trúc lạ — giữ nguyên, không ghi ra file có thể vỡ.
        }

        return null;
    }

    /**
     * Ghi XMP thẳng vào file trên đĩa (ghi file tạm rồi rename để không bao giờ
     * để lại ảnh ghi dở).
     *
     * @return bool true nếu file vừa được ghi thêm metadata
     */
    public static function applyToFile(string $path): bool
    {
        $data = @file_get_contents($path);
        if ($data === false || ($result = self::apply($data)) === null) {
            return false;
        }

        $temporary = $path . '.xmp-tmp';
        if (@file_put_contents($temporary, $result) !== strlen($result)) {
            @unlink($temporary);

            return false;
        }
        $perms = @fileperms($path);
        if (!@rename($temporary, $path)) {
            @unlink($temporary);

            return false;
        }
        if ($perms !== false) {
            @chmod($path, $perms & 0777);
        }

        return true;
    }

    private static function applyJpeg(string $data): string
    {
        $packet = self::XMP_NS_JPEG . self::xmpPacket();
        $length = strlen($data);
        $offset = 2;
        $head = '';

        // Giữ các segment APPn/COM đầu file (JFIF, EXIF, ICC…) trừ XMP cũ, rồi chèn
        // XMP mới ngay trước phần dữ liệu ảnh (DQT/SOF/…).
        while ($offset + 4 <= $length && $data[$offset] === "\xFF") {
            $marker = ord($data[$offset + 1]);
            if (!(($marker >= 0xE0 && $marker <= 0xEF) || $marker === 0xFE)) {
                break;
            }
            $size = unpack('n', substr($data, $offset + 2, 2))[1];
            if ($size < 2 || $offset + 2 + $size > $length) {
                throw new \UnexpectedValueException('Segment JPEG không hợp lệ');
            }
            $segment = substr($data, $offset, 2 + $size);
            $payload = substr($segment, 4);
            $isXmp = $marker === 0xE1 && (str_starts_with($payload, self::XMP_NS_JPEG) || str_starts_with($payload, self::XMP_EXT_JPEG));
            if (!$isXmp) {
                $head .= $segment;
            }
            $offset += 2 + $size;
        }

        return "\xFF\xD8" . $head . "\xFF\xE1" . pack('n', strlen($packet) + 2) . $packet . substr($data, $offset);
    }

    private static function applyPng(string $data): string
    {
        $length = strlen($data);
        $offset = 8;
        $chunks = [];

        while ($offset + 12 <= $length) {
            $size = unpack('N', substr($data, $offset, 4))[1];
            $type = substr($data, $offset + 4, 4);
            if ($offset + 12 + $size > $length) {
                throw new \UnexpectedValueException('Chunk PNG không hợp lệ');
            }
            $chunk = substr($data, $offset, 12 + $size);
            $isXmp = $type === 'iTXt' && str_starts_with(substr($chunk, 8), self::XMP_KEYWORD_PNG . "\0");
            if (!$isXmp) {
                $chunks[] = [$type, $chunk];
            }
            $offset += 12 + $size;
            if ($type === 'IEND') {
                break;
            }
        }
        if (!$chunks || $chunks[0][0] !== 'IHDR') {
            throw new \UnexpectedValueException('PNG thiếu IHDR');
        }

        // iTXt: keyword \0 compressionFlag compressionMethod languageTag \0 translatedKeyword \0 text
        $body = 'iTXt' . self::XMP_KEYWORD_PNG . "\0\0\0\0\0" . self::xmpPacket();
        $xmpChunk = pack('N', strlen($body) - 4) . $body . pack('N', crc32($body));

        $out = "\x89PNG\r\n\x1A\n" . $chunks[0][1] . $xmpChunk;
        foreach (array_slice($chunks, 1) as [, $chunk]) {
            $out .= $chunk;
        }

        return $out . substr($data, $offset);
    }

    private static function applyWebp(string $data): string
    {
        $length = strlen($data);
        $offset = 12;
        $chunks = [];

        while ($offset + 8 <= $length) {
            $fourCC = substr($data, $offset, 4);
            $size = unpack('V', substr($data, $offset + 4, 4))[1];
            $padded = $size + ($size & 1);
            if ($offset + 8 + $size > $length) {
                throw new \UnexpectedValueException('Chunk WebP không hợp lệ');
            }
            if ($fourCC !== 'XMP ') {
                $chunks[] = [$fourCC, substr($data, $offset + 8, $size)];
            }
            $offset += 8 + $padded;
        }
        if (!$chunks) {
            throw new \UnexpectedValueException('WebP rỗng');
        }

        // WebP "simple" (chỉ VP8/VP8L) không chứa được metadata: phải thêm chunk
        // VP8X ở đầu, khai báo kích thước canvas + cờ alpha lấy từ bitstream.
        if ($chunks[0][0] !== 'VP8X') {
            [$width, $height, $alpha] = self::webpBitstreamInfo($chunks[0][0], $chunks[0][1]);
            $vp8x = chr($alpha ? 0x10 : 0) . "\0\0\0"
                . substr(pack('V', $width - 1), 0, 3) . substr(pack('V', $height - 1), 0, 3);
            array_unshift($chunks, ['VP8X', $vp8x]);
        }
        $chunks[0][1][0] = chr(ord($chunks[0][1][0]) | 0x04); // cờ XMP
        $chunks[] = ['XMP ', self::xmpPacket()];

        $body = 'WEBP';
        foreach ($chunks as [$fourCC, $payload]) {
            $body .= $fourCC . pack('V', strlen($payload)) . $payload . (strlen($payload) & 1 ? "\0" : '');
        }

        return 'RIFF' . pack('V', strlen($body)) . $body;
    }

    /**
     * @return array{int, int, bool} [rộng, cao, có alpha]
     */
    private static function webpBitstreamInfo(string $fourCC, string $payload): array
    {
        if ($fourCC === 'VP8 ' && strlen($payload) >= 10 && substr($payload, 3, 3) === "\x9D\x01\x2A") {
            $dims = unpack('v2', substr($payload, 6, 4));

            return [$dims[1] & 0x3FFF, $dims[2] & 0x3FFF, false];
        }
        if ($fourCC === 'VP8L' && strlen($payload) >= 5 && $payload[0] === "\x2F") {
            $bits = unpack('V', substr($payload, 1, 4))[1];

            return [($bits & 0x3FFF) + 1, (($bits >> 14) & 0x3FFF) + 1, (bool) (($bits >> 28) & 1)];
        }

        throw new \UnexpectedValueException('Bitstream WebP không nhận dạng được');
    }
}
