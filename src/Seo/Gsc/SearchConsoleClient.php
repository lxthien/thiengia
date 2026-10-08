<?php

namespace App\Seo\Gsc;

/**
 * Client tối giản cho Search Console API (searchAnalytics.query), xác thực
 * bằng Service Account (JWT RS256 → access token). Dùng thẳng ext-curl +
 * ext-openssl để không phải kéo thêm google/apiclient cho đúng 2 request.
 */
class SearchConsoleClient
{
    private const SCOPE = 'https://www.googleapis.com/auth/webmasters.readonly';
    private const API_BASE = 'https://www.googleapis.com/webmasters/v3/sites/';
    // Giới hạn rowLimit của API cho mỗi request.
    public const PAGE_SIZE = 25000;

    private ?string $accessToken = null;
    private int $accessTokenExpiresAt = 0;

    public function __construct(
        private readonly string $credentialsPath,
        private readonly string $siteUrl,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->siteUrl !== '' && $this->credentialsPath !== '' && is_file($this->credentialsPath);
    }

    public function getSiteUrl(): string
    {
        return $this->siteUrl;
    }

    /**
     * Trả về từng dòng của searchAnalytics.query, tự phân trang tới khi hết.
     *
     * @param string[] $dimensions ví dụ ['date', 'page'] hoặc ['date', 'page', 'query']
     *
     * @return \Generator<int, array{keys: string[], clicks: float, impressions: float, ctr: float, position: float}>
     */
    public function searchAnalytics(\DateTimeInterface $from, \DateTimeInterface $to, array $dimensions): \Generator
    {
        $startRow = 0;

        do {
            $response = $this->request(
                self::API_BASE . rawurlencode($this->siteUrl) . '/searchAnalytics/query',
                json_encode([
                    'startDate' => $from->format('Y-m-d'),
                    'endDate' => $to->format('Y-m-d'),
                    'dimensions' => $dimensions,
                    'type' => 'web',
                    'dataState' => 'final',
                    'rowLimit' => self::PAGE_SIZE,
                    'startRow' => $startRow,
                ]),
                ['Content-Type: application/json', 'Authorization: Bearer ' . $this->getAccessToken()]
            );

            $rows = $response['rows'] ?? [];
            foreach ($rows as $row) {
                yield $row;
            }

            $startRow += self::PAGE_SIZE;
        } while (count($rows) === self::PAGE_SIZE);
    }

    private function getAccessToken(): string
    {
        if ($this->accessToken !== null && time() < $this->accessTokenExpiresAt - 60) {
            return $this->accessToken;
        }

        if (!$this->isConfigured()) {
            throw new \RuntimeException('Chưa cấu hình Search Console: kiểm tra GSC_SITE_URL và GSC_CREDENTIALS_PATH.');
        }

        $credentials = json_decode((string) file_get_contents($this->credentialsPath), true);
        if (!is_array($credentials) || empty($credentials['client_email']) || empty($credentials['private_key'])) {
            throw new \RuntimeException('File khóa Service Account không hợp lệ (thiếu client_email/private_key).');
        }

        $tokenUri = $credentials['token_uri'] ?? 'https://oauth2.googleapis.com/token';
        $now = time();
        $unsigned = self::base64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])) . '.' . self::base64Url(json_encode([
            'iss' => $credentials['client_email'],
            'scope' => self::SCOPE,
            'aud' => $tokenUri,
            'iat' => $now,
            'exp' => $now + 3600,
        ]));

        if (!openssl_sign($unsigned, $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256)) {
            throw new \RuntimeException('Không ký được JWT bằng private_key của Service Account.');
        }

        $response = $this->request(
            $tokenUri,
            http_build_query([
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $unsigned . '.' . self::base64Url($signature),
            ]),
            ['Content-Type: application/x-www-form-urlencoded']
        );

        if (empty($response['access_token'])) {
            throw new \RuntimeException('Google không trả về access_token.');
        }

        $this->accessToken = $response['access_token'];
        $this->accessTokenExpiresAt = $now + (int) ($response['expires_in'] ?? 3600);

        return $this->accessToken;
    }

    private function request(string $url, string $body, array $headers): array
    {
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 60,
        ]);

        $raw = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if ($raw === false) {
            throw new \RuntimeException('Không kết nối được tới Google: ' . $error);
        }

        $data = json_decode($raw, true);
        if ($status >= 400 || !is_array($data)) {
            $message = is_array($data)
                ? ($data['error']['message'] ?? $data['error_description'] ?? $raw)
                : $raw;

            throw new \RuntimeException(sprintf('Google API trả về lỗi HTTP %d: %s', $status, is_string($message) ? $message : json_encode($message)));
        }

        return $data;
    }

    private static function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
