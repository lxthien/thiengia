<?php

namespace App\Service;

use HTMLPurifier;
use HTMLPurifier_Config;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Lọc HTML cho các ô chữ ngắn mà template phải in ra bằng |raw — tiêu đề
 * khối trang chủ dùng <em> để tạo điểm nhấn của theme v3.
 *
 * Whitelist hẹp hơn nhiều so với ContentSanitizerListener (dành cho nội dung
 * CKEditor): chỉ nhấn mạnh và xuống dòng, không thẻ khối, không link, không
 * style — người nhập liệu không phá được layout.
 */
final class InlineHtmlPurifier
{
    private ?HTMLPurifier $purifier = null;

    public function __construct(
        #[Autowire(param: 'kernel.cache_dir')]
        private readonly string $cacheDir,
    ) {
    }

    public function purify(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return $this->getPurifier()->purify($value);
    }

    private function getPurifier(): HTMLPurifier
    {
        if ($this->purifier === null) {
            $cacheDir = $this->cacheDir . '/htmlpurifier';

            if (!is_dir($cacheDir)) {
                mkdir($cacheDir, 0775, true);
            }

            $config = HTMLPurifier_Config::createDefault();
            $config->set('Cache.SerializerPath', $cacheDir);
            $config->set('HTML.Allowed', 'em,strong,b,i,br');
            $config->set('AutoFormat.RemoveEmpty', true);

            $this->purifier = new HTMLPurifier($config);
        }

        return $this->purifier;
    }
}
