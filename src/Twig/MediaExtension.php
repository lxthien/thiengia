<?php

namespace App\Twig;

use App\Repository\MediaAssetRepository;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class MediaExtension extends AbstractExtension
{
    private array $cache = [];

    public function __construct(
        private readonly MediaAssetRepository $mediaAssetRepository,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('media_alt', [$this, 'getAlt']),
        ];
    }

    /**
     * Alt text đã lưu qua Media Library cho 1 ảnh, theo đường dẫn
     * post.images/tương tự. Trả về null nếu chưa từng đặt alt (kể cả với
     * ảnh cũ upload trước khi có Media Library, không có bản ghi tương
     * ứng) — nơi gọi tự lo fallback (thường về post.title).
     */
    public function getAlt(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        $relativePath = preg_replace('#^/?uploads/media/#', '', $path);

        if (!array_key_exists($relativePath, $this->cache)) {
            $asset = $this->mediaAssetRepository->findOneBy(['path' => $relativePath]);
            $this->cache[$relativePath] = $asset ? $asset->getAltText() : null;
        }

        return $this->cache[$relativePath];
    }
}
