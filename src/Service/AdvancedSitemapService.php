<?php

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;
use App\Entity\News;
use App\Entity\NewsCategory;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Advanced Sitemap Service with Caching Support
 */
class AdvancedSitemapService
{
    /**
     * @var EntityManager
     */
    private $em;

    /**
     * @var UrlGeneratorInterface
     */
    private $router;

    /**
     * @var CacheItemPoolInterface|null
     */
    private $cache;

    /**
     * Cache TTL in seconds (default: 1 hour)
     */
    private $cacheTtl = 3600;

    /**
     * Constructor
     *
     * @param EntityManager $em
     * @param UrlGeneratorInterface $router
     * @param CacheItemPoolInterface|null $cache Optional cache service
     */
    public function __construct(EntityManagerInterface $em, UrlGeneratorInterface $router, CacheItemPoolInterface $cache = null)
    {
        $this->em = $em;
        $this->router = $router;
        $this->cache = $cache;
    }

    /**
     * Set cache TTL
     *
     * @param int $ttl Time to live in seconds
     * @return self
     */
    public function setCacheTtl(int $ttl): self
    {
        $this->cacheTtl = $ttl;
        return $this;
    }

    /**
     * Generate sitemap array with optional caching
     *
     * @param bool $useCache Whether to use cache if available
     * @return array
     */
    public function generateSitemap(bool $useCache = true): array
    {
        $cacheKey = 'sitemap_urls';

        // Try to get from cache
        if ($useCache && $this->cache) {
            $cacheItem = $this->cache->getItem($cacheKey);
            if ($cacheItem->isHit()) {
                return $cacheItem->get();
            }
        }

        // Generate fresh sitemap
        $urls = [];

        // Add homepage
        $urls[] = $this->createUrlEntry(
            $this->router->generate('homepage', [], UrlGeneratorInterface::ABSOLUTE_URL),
            (new \DateTime())->format('Y-m-d'),
            'daily',
            '1.0'
        );

        // Add categories
        $urls = array_merge($urls, $this->getCategoryUrls());

        // Add news articles
        $urls = array_merge($urls, $this->getNewsUrls());

        // Cache the results if cache is available
        if ($this->cache) {
            $cacheItem = $this->cache->getItem($cacheKey);
            $cacheItem->set($urls);
            $cacheItem->expiresAfter($this->cacheTtl);
            $this->cache->save($cacheItem);
        }

        return $urls;
    }

    /**
     * Get category URLs
     *
     * @return array
     */
    private function getCategoryUrls(): array
    {
        $urls = [];
        $categories = $this->em->getRepository(NewsCategory::class)
            ->findBy(['enable' => true], ['createdAt' => 'DESC']);

        foreach ($categories as $category) {
            $urls[] = $this->createUrlEntry(
                $this->generateCategoryUrl($category),
                $this->getLastModDate($category->getUpdatedAt(), $category->getCreatedAt()),
                'weekly',
                '0.8'
            );
        }

        return $urls;
    }

    /**
     * Get news URLs
     *
     * @return array
     */
    private function getNewsUrls(): array
    {
        $urls = [];
        $news = $this->em->getRepository(News::class)
            ->findBy(['enable' => true], ['createdAt' => 'DESC']);

        foreach ($news as $article) {
            $urls[] = $this->createUrlEntry(
                $this->generateNewsUrl($article),
                $this->getLastModDate($article->getUpdatedAt(), $article->getCreatedAt()),
                'weekly',
                '0.9'
            );
        }

        return $urls;
    }

    /**
     * Create URL entry array
     *
     * @param string $url
     * @param string $lastmod
     * @param string $changefreq
     * @param string $priority
     * @return array
     */
    private function createUrlEntry(string $url, string $lastmod, string $changefreq, string $priority): array
    {
        return [
            'url' => $url,
            'lastmod' => $lastmod,
            'changefreq' => $changefreq,
            'priority' => $priority
        ];
    }

    /**
     * Generate URL for a category page
     *
     * @param NewsCategory $category
     * @return string
     */
    private function generateCategoryUrl(NewsCategory $category): string
    {
        $baseUrl = $this->generateBaseUrl();

        if ($category->getParentcat()) {
            return $baseUrl . $category->getParentcat()->getUrl() . '/' . $category->getUrl() . '/';
        }

        return $baseUrl . $category->getUrl() . '/';
    }

    /**
     * Generate URL for a news article
     *
     * @param News $article
     * @return string
     */
    private function generateNewsUrl(News $article): string
    {
        $baseUrl = $this->generateBaseUrl();
        $categories = $article->getCategory();

        if ($categories && count($categories) > 0) {
            $primaryCategory = $categories[0];

            if ($primaryCategory->getParentcat()) {
                return $baseUrl . 
                    $primaryCategory->getParentcat()->getUrl() . '/' . 
                    $primaryCategory->getUrl() . '/' . 
                    $article->getUrl();
            }

            return $baseUrl . $primaryCategory->getUrl() . '/' . $article->getUrl();
        }

        return $baseUrl . $article->getUrl();
    }

    /**
     * Generate base URL
     *
     * @return string
     */
    private function generateBaseUrl(): string
    {
        return $this->router->generate('homepage', [], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    /**
     * Get last modification date
     *
     * @param \DateTime|null $updated
     * @param \DateTime|null $created
     * @return string
     */
    private function getLastModDate(\DateTime $updated, \DateTime $created): string
    {
        $date = $updated ?? $created;
        
        if (!$date) {
            return (new \DateTime())->format('Y-m-d');
        }

        return $date->format('Y-m-d');
    }

    /**
     * Clear sitemap cache
     *
     * @return bool
     */
    public function clearCache(): bool
    {
        if ($this->cache) {
            return $this->cache->deleteItem('sitemap_urls');
        }

        return false;
    }

    /**
     * Get sitemap statistics
     *
     * @return array
     */
    public function getStatistics(): array
    {
        $urls = $this->generateSitemap(false);

        $stats = [
            'total_urls' => count($urls),
            'homepage' => 1,
            'categories' => 0,
            'articles' => 0,
            'change_frequencies' => [
                'daily' => 0,
                'weekly' => 0,
                'monthly' => 0,
            ],
            'priority_distribution' => [
                '1.0' => 0,
                '0.9' => 0,
                '0.8' => 0,
                '0.7' => 0,
                'other' => 0,
            ]
        ];

        foreach ($urls as $url) {
            $changefreq = $url['changefreq'];
            if (isset($stats['change_frequencies'][$changefreq])) {
                $stats['change_frequencies'][$changefreq]++;
            }

            $priority = $url['priority'];
            if (isset($stats['priority_distribution'][$priority])) {
                $stats['priority_distribution'][$priority]++;
            } else {
                $stats['priority_distribution']['other']++;
            }
        }

        // Count articles and categories
        $stats['categories'] = $this->em->getRepository(NewsCategory::class)
            ->count(['enable' => true]);
        $stats['articles'] = $this->em->getRepository(News::class)
            ->count(['enable' => true]);

        return $stats;
    }
}
