<?php

namespace App\Service;

use App\Enum\SectionType;
use App\Repository\HomepageSectionRepository;

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
     * Nguồn là nhóm "Thông tin website" ở /admin/settings. Các key
     * `content_*` (nhóm "Biến nội dung") chỉ dùng để thay placeholder trong
     * nội dung bài viết, không dùng cho giao diện.
     */
    private const OVERRIDES = [
        'company.shortNameOnly' => ['siteName'],
        'company.address'       => ['companyAddress'],
        'company.taxCode'       => ['companyTax'],
        'company.workingHours'  => ['companyWorkingHours'],
        'company.phone'         => ['hotLine1'],
        'company.email'         => ['emailContact'],
    ];

    private ?array $config = null;

    public function __construct(
        private readonly array $defaults,
        private readonly SettingsManager $settingsManager,
        private readonly HomepageSectionRepository $homepageSections,
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

        if ($phoneRaw !== '') {
            $config['company']['zalo'] = 'https://zalo.me/' . preg_replace('/^\+84/', '0', $phoneRaw);
        }

        // Shared statistics: the homepage editor is the single source for all pages.
        // Keep the configured values even when the strip is hidden on the homepage.
        try {
            $items = $this->homepageSections->findOneByType(SectionType::Figures)?->getConfigValue('items');
            if (is_array($items) && $items !== []) {
                $config['figures'] = $items;
            }
        } catch (\Throwable) {
            // Installation/error rendering may run before the homepage table is available.
            // In that case the YAML defaults remain usable.
        }

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
