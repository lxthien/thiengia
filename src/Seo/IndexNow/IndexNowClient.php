<?php

namespace App\Seo\IndexNow;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Báo cho các công cụ tìm kiếm hỗ trợ IndexNow (Bing, Yandex, Seznam,
 * Naver...) rằng một URL vừa được thêm, sửa hoặc xóa. Google không tham gia
 * IndexNow — Google vẫn lấy thay đổi qua sitemap.
 *
 * Bật bằng cách đặt INDEXNOW_KEY (8–128 ký tự a-f, 0-9) trong .env.local.
 * Công cụ tìm kiếm xác minh quyền sở hữu qua file https://<site>/<key>.txt,
 * do SitemapController::indexNowKeyAction phục vụ.
 *
 * Giao thức: https://www.indexnow.org/documentation
 */
final class IndexNowClient
{
    private const ENDPOINT = 'https://api.indexnow.org/indexnow';

    /** Giới hạn của giao thức cho một lần gửi. */
    private const MAX_URLS = 10000;

    public function __construct(
        #[Autowire('%env(INDEXNOW_KEY)%')]
        private readonly string $key,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function isEnabled(): bool
    {
        return self::isValidKey($this->key);
    }

    public function getKey(): string
    {
        return trim($this->key);
    }

    public static function isValidKey(string $key): bool
    {
        return (bool) preg_match('/^[a-f0-9]{8,128}$/', trim($key));
    }

    /**
     * @param list<string> $urls URL tuyệt đối, cùng một tên miền
     *
     * @return array{ok: bool, status: int, sent: int, message: string}
     */
    public function submit(array $urls): array
    {
        $urls = array_values(array_unique(array_filter(array_map('trim', $urls))));

        if (!$this->isEnabled()) {
            return ['ok' => false, 'status' => 0, 'sent' => 0, 'message' => 'Chưa cấu hình INDEXNOW_KEY.'];
        }

        if ($urls === []) {
            return ['ok' => true, 'status' => 0, 'sent' => 0, 'message' => 'Không có URL nào để gửi.'];
        }

        $host = (string) parse_url($urls[0], PHP_URL_HOST);
        $scheme = (string) (parse_url($urls[0], PHP_URL_SCHEME) ?: 'https');

        // Máy dev: gửi URL localhost lên IndexNow chỉ nhận về lỗi.
        if ($host === '' || in_array($host, ['localhost', '127.0.0.1', '::1'], true) || str_ends_with($host, '.test') || str_ends_with($host, '.local')) {
            return ['ok' => false, 'status' => 0, 'sent' => 0, 'message' => sprintf('Bỏ qua: "%s" không phải tên miền công khai.', $host)];
        }

        $urls = array_slice(array_values(array_filter($urls, static fn (string $url): bool => parse_url($url, PHP_URL_HOST) === $host)), 0, self::MAX_URLS);

        $payload = json_encode([
            'host' => $host,
            'key' => $this->getKey(),
            'keyLocation' => $scheme . '://' . $host . '/' . $this->getKey() . '.txt',
            'urlList' => $urls,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $curl = curl_init(self::ENDPOINT);
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json; charset=utf-8'],
            CURLOPT_RETURNTRANSFER => true,
            // Chạy sau khi đã trả response cho người dùng, nhưng vẫn giữ ngắn
            // để không treo tiến trình PHP khi IndexNow chậm.
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_TIMEOUT => 8,
        ]);

        $body = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        // 200: đã nhận; 202: đã nhận, đang chờ xác minh file khóa.
        $ok = in_array($status, [200, 202], true);
        $message = match (true) {
            $ok => $status === 202 ? 'Đã nhận, IndexNow đang xác minh file khóa.' : 'Đã nhận.',
            $status === 0 => 'Không kết nối được: ' . $error,
            $status === 403 => 'Khóa không hợp lệ: IndexNow không đọc được file /<key>.txt trên site.',
            $status === 422 => 'URL không thuộc tên miền đã khai báo hoặc khóa không khớp.',
            $status === 429 => 'Gửi quá nhiều, bị giới hạn tạm thời.',
            default => 'IndexNow trả về mã ' . $status . ($body ? ': ' . mb_substr((string) $body, 0, 200) : ''),
        };

        $this->logger->log($ok ? 'info' : 'warning', 'IndexNow: {message} ({count} URL, HTTP {status})', [
            'message' => $message,
            'count' => count($urls),
            'status' => $status,
            'urls' => array_slice($urls, 0, 20),
        ]);

        return ['ok' => $ok, 'status' => $status, 'sent' => count($urls), 'message' => $message];
    }
}
