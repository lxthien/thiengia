<?php

namespace App\Service;

/**
 * Nguồn đọc cấu hình theme v3.
 *
 * Giá trị mặc định nằm ở parameter `v3_defaults` (config/packages/v3.yaml),
 * sau đó được phủ lên bằng dữ liệu thật trong bảng `settings`. Template dùng
 * qua biến global `v3` (App\Twig\V3ConfigExtension); PHP dùng service này để
 * khỏi hardcode lại nội dung đã có trong cấu hình.
 */
final class V3Config
{
    /**
     * Đường dẫn trong cấu hình => danh sách setting key theo thứ tự ưu tiên.
     * Key đầu tiên có giá trị không rỗng sẽ được dùng.
     *
     * TẠM ĐỂ TRỐNG — chưa chốt nguồn thông tin công ty.
     *
     * Bảng `settings` của DB dev (kientruc_v3) đang giữ thông tin của một
     * thương hiệu khác do DB được nhân bản, trong khi theme v3 chưa bao giờ
     * đọc các key `contens_*` (chỉ bộ template cũ templates/layout/* dùng,
     * mà bộ đó không còn được include). Bật map dưới đây khi thông tin công
     * ty trong settings đã đúng là của Thiện Gia:
     *
     *   'company.legalName' => ['contens_ten_day_du'],
     *   'company.shortName' => ['contens_ten_cong_ty'],
     *   'company.address'   => ['contens_dia_chi'],
     *   'company.phone'     => ['contens_hotline_1', 'hotLine1'],
     *   'company.email'     => ['contens_email', 'emailContact'],
     *   'company.taxCode'   => ['contens_mst'],
     */
    private const OVERRIDES = [];

    private ?array $config = null;

    public function __construct(
        private readonly array $defaults,
        private readonly SettingsManager $settingsManager,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->config ??= $this->build();
    }

    /**
     * Đọc theo đường dẫn phân cách bằng dấu chấm, vd: get('menu.services', []).
     */
    public function get(string $path, mixed $default = null): mixed
    {
        $cursor = $this->all();

        foreach (explode('.', $path) as $segment) {
            if (!is_array($cursor) || !array_key_exists($segment, $cursor)) {
                return $default;
            }

            $cursor = $cursor[$segment];
        }

        return $cursor;
    }

    private function build(): array
    {
        $config = $this->defaults;

        try {
            foreach (self::OVERRIDES as $path => $keys) {
                foreach ($keys as $key) {
                    $value = trim((string) $this->settingsManager->get($key, ''));

                    if ($value !== '') {
                        $this->setPath($config, $path, $value);

                        break;
                    }
                }
            }
        } catch (\Throwable) {
            // DB chưa sẵn sàng (cache:clear lúc cài đặt, console command...)
            // — dùng nguyên giá trị mặc định thay vì làm chết request.
            return $this->defaults;
        }

        // Link tel: phải suy ra từ số điện thoại đang hiệu lực, nếu không
        // admin đổi hotline trong settings mà nút gọi vẫn giữ số cũ.
        $phoneRaw = self::toTelNumber($config['company']['phone'] ?? '');
        $config['company']['phoneRaw'] = $phoneRaw ?: ($this->defaults['company']['phoneRaw'] ?? '');

        return $config;
    }

    private function setPath(array &$config, string $path, string $value): void
    {
        $cursor = &$config;

        foreach (explode('.', $path) as $segment) {
            if (!is_array($cursor)) {
                return;
            }

            $cursor = &$cursor[$segment];
        }

        $cursor = $value;
    }

    /**
     * '0901 234 567' => '+84901234567'. Trả về '' nếu không nhận dạng được.
     */
    private static function toTelNumber(string $phone): string
    {
        $digits = preg_replace('/[^0-9+]/', '', $phone) ?? '';

        if (str_starts_with($digits, '+')) {
            return $digits;
        }

        if (str_starts_with($digits, '84')) {
            return '+' . $digits;
        }

        if (str_starts_with($digits, '0')) {
            return '+84' . substr($digits, 1);
        }

        return '';
    }
}
