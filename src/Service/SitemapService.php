<?php

namespace App\Service;

use App\Entity\News;
use App\Entity\NewsCategory;
use App\Enum\PostStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Creates standards-compliant, bounded sitemap files.
 *
 * Search engines allow up to 50,000 URLs in one sitemap. Keeping each file at
 * 45,000 URLs leaves headroom and prevents one large CMS from loading every
 * published entity into PHP memory on each sitemap request.
 */
class SitemapService
{
    public const URLS_PER_SITEMAP = 45000;

    public const MANIFEST_CACHE_KEY = 'app.seo.sitemap.manifest';
    private const MANIFEST_TTL_SECONDS = 300;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UrlGeneratorInterface $router,
        private readonly CacheInterface $cache,
    ) {
    }

    /**
     * Backward-compatible aggregate API. Controllers should render one page
     * at a time through generateSitemapPage() instead.
     *
     * @return array<int, array<string, string>>
     */
    public function generateSitemap(): array
    {
        $urls = [];

        for ($page = 1; $page <= $this->getSitemapPageCount(); $page++) {
            $urls = array_merge($urls, $this->generateSitemapPage($page));
        }

        return $urls;
    }

    public function getSitemapPageCount(): int
    {
        return max(1, (int) ceil($this->getUrlCount() / self::URLS_PER_SITEMAP));
    }

    public function getUrlCount(): int
    {
        return $this->getManifest()['urlCount'];
    }

    /**
     * A version for HTTP ETags. It changes after an eligible post/category is
     * created, edited, published, disabled or toggled noindex.
     */
    public function getVersion(): string
    {
        return $this->getManifest()['version'];
    }

    /**
     * @return array<int, array{url: string, lastmod: string}>
     */
    public function generateSitemapIndex(): array
    {
        $lastmod = $this->getManifest()['lastmod'];
        $items = [];

        for ($page = 1; $page <= $this->getSitemapPageCount(); $page++) {
            $items[] = [
                'url' => $this->router->generate('sitemap_page', ['page' => $page], UrlGeneratorInterface::ABSOLUTE_URL),
                'lastmod' => $lastmod,
            ];
        }

        return $items;
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function generateSitemapPage(int $page): array
    {
        if ($page < 1 || $page > $this->getSitemapPageCount()) {
            return [];
        }

        $offset = ($page - 1) * self::URLS_PER_SITEMAP;
        $remaining = self::URLS_PER_SITEMAP;
        $urls = [];
        $staticUrls = $this->getStaticUrls();

        if ($offset < count($staticUrls)) {
            $staticSlice = array_slice($staticUrls, $offset, $remaining);
            $urls = $staticSlice;
            $remaining -= count($staticSlice);
        }

        if ($remaining === 0) {
            return $urls;
        }

        $contentOffset = max(0, $offset - count($staticUrls));
        $manifest = $this->getManifest();

        if ($contentOffset < $manifest['categoryCount']) {
            $categories = $this->findSitemapCategories($contentOffset, $remaining);

            foreach ($categories as $category) {
                $urls[] = [
                    'url' => $this->generateCategoryUrl($category),
                    'lastmod' => $this->formatLastModified($category->getUpdatedAt(), $category->getCreatedAt()),
                    'changefreq' => 'weekly',
                    'priority' => '0.8',
                ];
            }

            $remaining -= count($categories);
        }

        if ($remaining === 0) {
            return $urls;
        }

        $postOffset = max(0, $contentOffset - $manifest['categoryCount']);
        $posts = $this->findSitemapPosts($postOffset, $remaining);

        foreach ($posts as $article) {
            $entry = [
                'url' => $this->generateNewsUrl($article),
                'lastmod' => $this->formatLastModified($article->getUpdatedAt(), $article->getCreatedAt()),
                'changefreq' => 'weekly',
                'priority' => '0.9',
            ];

            if ($article->getImages()) {
                $entry['image'] = $this->generateBaseUrl() . ltrim($article->getImages(), '/');
            }

            $urls[] = $entry;
        }

        return $urls;
    }

    /**
     * @return array{urlCount: int, categoryCount: int, postCount: int, lastmod: string, version: string}
     */
    private function getManifest(): array
    {
        return $this->cache->get(self::MANIFEST_CACHE_KEY, function (ItemInterface $item): array {
            $item->expiresAfter(self::MANIFEST_TTL_SECONDS);

            $categoryCount = (int) $this->em->getRepository(NewsCategory::class)->createQueryBuilder('c')
                ->select('COUNT(c.id)')
                ->where('c.enable = :enabled')
                ->andWhere('c.metaIndex = :indexable')
                ->setParameter('enabled', true)
                ->setParameter('indexable', true)
                ->getQuery()
                ->getSingleScalarResult();

            $postCount = (int) $this->em->getRepository(News::class)->createQueryBuilder('n')
                ->select('COUNT(n.id)')
                ->where('n.status = :status')
                ->andWhere('n.postType IN (:postTypes)')
                ->andWhere('n.metaIndex = :indexable')
                ->setParameter('status', PostStatus::Published)
                ->setParameter('postTypes', ['post', 'page'])
                ->setParameter('indexable', true)
                ->getQuery()
                ->getSingleScalarResult();

            $latestCategory = (string) $this->em->getRepository(NewsCategory::class)->createQueryBuilder('c')
                ->select('MAX(c.updatedAt)')
                ->getQuery()
                ->getSingleScalarResult();
            $latestPost = (string) $this->em->getRepository(News::class)->createQueryBuilder('n')
                ->select('MAX(n.updatedAt)')
                ->getQuery()
                ->getSingleScalarResult();
            $lastmod = substr(max($latestCategory, $latestPost), 0, 10) ?: (new \DateTimeImmutable())->format('Y-m-d');
            $urlCount = count($this->getStaticUrls()) + $categoryCount + $postCount;

            return [
                'urlCount' => $urlCount,
                'categoryCount' => $categoryCount,
                'postCount' => $postCount,
                'lastmod' => $lastmod,
                'version' => hash('sha256', implode('|', [$urlCount, $categoryCount, $postCount, $latestCategory, $latestPost])),
            ];
        });
    }

    /** @return NewsCategory[] */
    private function findSitemapCategories(int $offset, int $limit): array
    {
        return $this->em->getRepository(NewsCategory::class)->createQueryBuilder('c')
            ->where('c.enable = :enabled')
            ->andWhere('c.metaIndex = :indexable')
            ->setParameter('enabled', true)
            ->setParameter('indexable', true)
            ->orderBy('c.id', 'ASC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /** @return News[] */
    private function findSitemapPosts(int $offset, int $limit): array
    {
        return $this->em->getRepository(News::class)->createQueryBuilder('n')
            ->where('n.status = :status')
            ->andWhere('n.postType IN (:postTypes)')
            ->andWhere('n.metaIndex = :indexable')
            ->setParameter('status', PostStatus::Published)
            ->setParameter('postTypes', ['post', 'page'])
            ->setParameter('indexable', true)
            ->orderBy('n.id', 'ASC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /** @return array<int, array{url: string, lastmod: string, changefreq: string, priority: string}> */
    private function getStaticUrls(): array
    {
        $today = (new \DateTimeImmutable())->format('Y-m-d');

        return [
            ['url' => $this->router->generate('homepage', [], UrlGeneratorInterface::ABSOLUTE_URL), 'lastmod' => $today, 'changefreq' => 'daily', 'priority' => '1.0'],
            ['url' => $this->router->generate('caculator_cost_construction', [], UrlGeneratorInterface::ABSOLUTE_URL), 'lastmod' => $today, 'changefreq' => 'monthly', 'priority' => '0.5'],
            ['url' => $this->router->generate('contact', [], UrlGeneratorInterface::ABSOLUTE_URL), 'lastmod' => $today, 'changefreq' => 'monthly', 'priority' => '0.5'],
            ['url' => $this->router->generate('building_density_calculator', [], UrlGeneratorInterface::ABSOLUTE_URL), 'lastmod' => $today, 'changefreq' => 'monthly', 'priority' => '0.5'],
            ['url' => $this->router->generate('building_age_calculator', [], UrlGeneratorInterface::ABSOLUTE_URL), 'lastmod' => $today, 'changefreq' => 'monthly', 'priority' => '0.5'],
            ['url' => $this->router->generate('house_direction_calculator', [], UrlGeneratorInterface::ABSOLUTE_URL), 'lastmod' => $today, 'changefreq' => 'monthly', 'priority' => '0.5'],
        ];
    }

    private function generateCategoryUrl(NewsCategory $category): string
    {
        if ($category->getParentcat() !== null) {
            return $this->generateBaseUrl() . $category->getParentcat()->getUrl() . '/' . $category->getUrl() . '/';
        }

        return $this->generateBaseUrl() . $category->getUrl() . '/';
    }

    private function generateNewsUrl(News $article): string
    {
        return $this->generateBaseUrl() . $article->getUrl() . '/';
    }

    private function generateBaseUrl(): string
    {
        return rtrim($this->router->generate('homepage', [], UrlGeneratorInterface::ABSOLUTE_URL), '/') . '/';
    }

    private function formatLastModified(?\DateTimeInterface $updatedAt, ?\DateTimeInterface $createdAt): string
    {
        return ($updatedAt ?: $createdAt ?: new \DateTimeImmutable())->format('Y-m-d');
    }
}
