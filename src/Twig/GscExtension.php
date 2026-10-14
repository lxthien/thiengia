<?php

namespace App\Twig;

use App\Seo\Gsc\GscStatsReader;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Số liệu Search Console cho màn hình sửa bài viết/trang — xem
 * templates/admin/components/_gsc_panel.html.twig.
 */
class GscExtension extends AbstractExtension
{
    public function __construct(
        private readonly GscStatsReader $reader,
        #[Autowire('%env(GSC_SITE_URL)%')]
        private readonly string $siteUrl,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('gsc_post_stats', [$this, 'postStats']),
            new TwigFunction('gsc_configured', [$this, 'isConfigured']),
        ];
    }

    public function postStats(?string $slug, int $days = 28): array
    {
        return $this->reader->forSlug((string) $slug, $days);
    }

    public function isConfigured(): bool
    {
        return trim($this->siteUrl) !== '';
    }
}
