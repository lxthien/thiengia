<?php

namespace App\Twig;

use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Liip\ImagineBundle\Service\FilterService;
use Symfony\Component\Asset\Packages;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Twig\Extension\AbstractExtension;
use Twig\Markup;
use Twig\TwigFunction;

/**
 * Ảnh responsive cho theme v3: ảnh gốc trong public/ (thường 2–3 MB) được
 * LiipImagine thu nhỏ theo vài bề rộng cố định và xuất thêm bản WebP; trình
 * duyệt tự chọn bản vừa với màn hình qua srcset/sizes.
 *
 *   {{ v3_image(path, 'card', {alt: '...', class: '...'}) }}
 *   {{ v3_image_url(path, 1920) }}
 *
 * Bản WebP được tạo ở lần gọi đầu tiên (URL /media/cache/resolve/...), sau đó
 * hàm này trỏ thẳng tới file tĩnh .webp trong public/media/cache. Cần
 * `liip_imagine.webp.generate: true` và các filter set v3_<width>.
 *
 * Ảnh ngoài site, SVG/GIF hoặc file không tồn tại thì in thẻ <img> thường.
 */
final class V3ImageExtension extends AbstractExtension
{
    /** Phải khớp với các filter set v3_<width> trong config/packages/framework.yaml. */
    private const WIDTHS = [400, 640, 800, 960, 1280, 1920];

    /** Bề rộng dùng cho thuộc tính src (trình duyệt không hỗ trợ srcset). */
    private const DEFAULT_WIDTH = 960;

    /**
     * `sizes` phải sát bề rộng ảnh thật sự hiển thị, nếu không trình duyệt
     * tải bản lớn hơn cần thiết (PageSpeed: "larger than it needs to be").
     * Nội dung nằm trong container max-w-7xl (1280px) nên từ 1280px trở lên
     * dùng số px cố định. Mốc 800 dành cho điện thoại màn hình mật độ cao
     * (412px × 1.75 ≈ 720px).
     */
    private const PRESETS = [
        // Ảnh tràn hết chiều ngang (hero, nền CTA)
        'hero' => ['widths' => [640, 800, 960, 1280, 1920], 'sizes' => '100vw'],
        // Ảnh chiếm khoảng nửa container trên desktop (giới thiệu)
        'half' => ['widths' => [400, 640, 800, 960, 1280], 'sizes' => '(min-width: 1280px) 600px, (min-width: 1024px) 46vw, 100vw'],
        // Ảnh chiếm khoảng một phần ba container trên desktop (cam kết)
        'third' => ['widths' => [400, 640, 800, 960], 'sizes' => '(min-width: 1280px) 470px, (min-width: 1024px) 36vw, 100vw'],
        // Thẻ trong lưới 3–4 cột (dịch vụ, công trình, thư viện ảnh)
        'card' => ['widths' => [400, 640, 800], 'sizes' => '(min-width: 1280px) 300px, (min-width: 1024px) 24vw, (min-width: 640px) 46vw, 76vw'],
        // Ảnh đại diện nhỏ
        'avatar' => ['widths' => [400], 'sizes' => '96px'],
    ];

    /** Filter set ảnh chia sẻ MXH 1200×630 trong config/packages/framework.yaml. */
    private const SHARE_FILTER = 'news_share_1200_630';

    public function __construct(
        private readonly CacheManager $cacheManager,
        private readonly FilterService $filterService,
        private readonly Packages $packages,
        private readonly CacheInterface $cache,
        #[Autowire('%kernel.project_dir%/public')]
        private readonly string $publicDir,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('v3_image', [$this, 'image'], ['is_safe' => ['html']]),
            new TwigFunction('v3_image_url', [$this, 'imageUrl']),
            new TwigFunction('v3_share_image', [$this, 'shareImage']),
        ];
    }

    /**
     * Bản 1200×630 (tỉ lệ 1.91:1 của Facebook/Zalo) cắt giữa từ ảnh đại diện, dạng JPG.
     * Tạo ngay ở lần gọi đầu và trả URL file tĩnh — crawler MXH không phải đi qua
     * URL resolve/redirect. Trả null nếu không phải ảnh nội bộ hoặc tạo lỗi
     * (template khi đó dùng ảnh gốc).
     *
     * @return array{url: string, width: int, height: int}|null
     */
    public function shareImage(?string $path): ?array
    {
        $relative = $this->localPath($path);

        if ($relative === null) {
            return null;
        }

        try {
            $url = $this->filterService->getUrlOfFilteredImage($relative, self::SHARE_FILTER);
        } catch (\Throwable) {
            return null;
        }

        // Ảnh gốc nhỏ hơn 1200×630 thì Liip không phóng to — đọc kích thước thật của file đã tạo.
        $file = $this->publicDir . '/' . ltrim((string) parse_url($url, PHP_URL_PATH), '/');
        [$width, $height] = is_file($file) ? $this->dimensionsOf($file) : [0, 0];

        return ['url' => $url, 'width' => $width ?: 1200, 'height' => $height ?: 630];
    }

    /**
     * @param array{alt?: ?string, class?: ?string, lazy?: bool, priority?: bool, attr?: array<string, string>} $options
     */
    public function image(?string $path, string $preset = 'half', array $options = []): Markup
    {
        $priority = (bool) ($options['priority'] ?? false);
        $attributes = [
            'alt' => (string) ($options['alt'] ?? ''),
            'class' => $options['class'] ?? null,
            'decoding' => 'async',
        ] + ($options['attr'] ?? []);

        if ($priority) {
            // Ảnh quyết định LCP: tải ngay, ưu tiên cao.
            $attributes['fetchpriority'] = 'high';
        } elseif ($options['lazy'] ?? true) {
            $attributes['loading'] = 'lazy';
        }

        $relative = $this->localPath($path);

        if ($relative === null) {
            return $this->tag(['src' => $this->plainUrl($path)] + $attributes);
        }

        $config = self::PRESETS[$preset] ?? self::PRESETS['half'];
        [$originalWidth, $originalHeight] = $this->dimensions($relative);

        // Ảnh gốc hẹp hơn một mốc thì bản ở mốc đó giữ nguyên bề rộng gốc
        // (filter downscale không phóng to) — gộp các mốc trùng nhau lại.
        $variants = [];

        foreach ($config['widths'] as $width) {
            $actual = $originalWidth > 0 ? min($width, $originalWidth) : $width;
            $variants[$actual] ??= $this->variantUrl($relative, $width);
        }

        ksort($variants);

        $srcset = [];

        foreach ($variants as $width => $url) {
            $srcset[] = $url . ' ' . $width . 'w';
        }

        $largest = (int) array_key_last($variants);
        $default = $variants[min(self::DEFAULT_WIDTH, $largest)] ?? $variants[$largest];

        $sized = [];

        if ($originalWidth > 0 && $originalHeight > 0) {
            // width/height để trình duyệt giữ chỗ trước khi ảnh tải xong (tránh CLS).
            $sized = ['width' => (string) $largest, 'height' => (string) (int) round($originalHeight * $largest / $originalWidth)];
        }

        return $this->tag([
            'src' => $default,
            'srcset' => implode(', ', $srcset),
            'sizes' => $config['sizes'],
        ] + $sized + $attributes);
    }

    /**
     * URL của một bản thu nhỏ đơn lẻ (vd ảnh phóng to trong lightbox).
     */
    public function imageUrl(?string $path, int $width = 1920): string
    {
        $relative = $this->localPath($path);

        if ($relative === null) {
            return $this->plainUrl($path);
        }

        $width = in_array($width, self::WIDTHS, true) ? $width : 1920;

        return $this->variantUrl($relative, $width);
    }

    private function variantUrl(string $relative, int $width): string
    {
        $filter = 'v3_' . $width;
        $webp = $relative . '.webp';

        // Đã có file WebP trong public/media/cache → trỏ thẳng (Nginx phục vụ
        // tĩnh, đúng Content-Type image/webp). Chưa có → URL resolve: Liip tạo
        // ảnh rồi chuyển hướng sang bản .webp nếu trình duyệt gửi Accept webp.
        if ($this->cacheManager->isStored($webp, $filter)) {
            return $this->cacheManager->resolve($webp, $filter);
        }

        return $this->cacheManager->generateUrl($relative, $filter, [], null, UrlGeneratorInterface::ABSOLUTE_PATH);
    }

    /**
     * Đường dẫn tương đối theo public/ nếu $path là ảnh raster có thật trên đĩa.
     */
    private function localPath(?string $path): ?string
    {
        $path = trim((string) $path);

        if ($path === '' || preg_match('#^(?:[a-z][a-z0-9+.-]*:)?//#i', $path) || str_starts_with($path, 'data:')) {
            return null;
        }

        $relative = ltrim((string) strtok($path, '?#'), '/');

        if (str_contains($relative, '..') || !preg_match('/\.(?:jpe?g|png|webp)$/i', $relative)) {
            return null;
        }

        return is_file($this->publicDir . '/' . $relative) ? $relative : null;
    }

    private function plainUrl(?string $path): string
    {
        $path = trim((string) $path);

        if ($path === '' || preg_match('#^(?:[a-z][a-z0-9+.-]*:)?//#i', $path) || str_starts_with($path, 'data:') || str_starts_with($path, '/')) {
            return $path;
        }

        return $this->packages->getUrl($path);
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function dimensions(string $relative): array
    {
        return $this->dimensionsOf($this->publicDir . '/' . $relative);
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function dimensionsOf(string $file): array
    {
        $key = 'v3_image_dim_' . md5(substr($file, strlen($this->publicDir)) . '|' . (string) @filemtime($file));

        return $this->cache->get($key, static function () use ($file): array {
            $size = @getimagesize($file);

            return $size ? [(int) $size[0], (int) $size[1]] : [0, 0];
        });
    }

    /**
     * @param array<string, ?string> $attributes
     */
    private function tag(array $attributes): Markup
    {
        $html = '<img';

        foreach ($attributes as $name => $value) {
            if ($value === null || ($value === '' && $name !== 'alt')) {
                continue;
            }

            $html .= ' ' . $name . '="' . htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
        }

        return new Markup($html . '>', 'UTF-8');
    }
}
