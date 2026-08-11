<?php

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;
use App\Entity\News;
use App\Entity\NewsCategory;
use App\Enum\PostStatus;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class SitemapService
{
    /**
     * @var EntityManagerInterface
     */
    private $em;

    /**
     * @var UrlGeneratorInterface
     */
    private $router;

    /**
     * Constructor
     *
     * @param EntityManagerInterface $em
     * @param UrlGeneratorInterface $router
     */
    public function __construct(EntityManagerInterface $em, UrlGeneratorInterface $router)
    {
        $this->em = $em;
        $this->router = $router;
    }

    /**
     * Generate sitemap array with all URLs
     *
     * @return array
     */
    public function generateSitemap()
    {
        $urls = [];

        // Add homepage
        $urls[] = [
            'url' => $this->router->generate('homepage', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'lastmod' => (new \DateTime())->format('Y-m-d'),
            'changefreq' => 'daily',
            'priority' => '1.0'
        ];

        // Get all enabled categories
        $categories = $this->em->getRepository(NewsCategory::class)
            ->findBy(['enable' => true], ['createdAt' => 'DESC']);

        foreach ($categories as $category) {
            // Skip categories with noindex or nofollow in robots field
            $robots = $category->getRobots();
            if ($robots && (stripos($robots, 'noindex') !== false || stripos($robots, 'nofollow') !== false)) {
                continue;
            }

            $urls[] = [
                'url' => $this->generateCategoryUrl($category),
                'lastmod' => $category->getUpdatedAt() ? $category->getUpdatedAt()->format('Y-m-d') : $category->getCreatedAt()->format('Y-m-d'),
                'changefreq' => 'weekly',
                'priority' => '0.8'
            ];
        }

        // Get all published news articles
        $news = $this->em->getRepository(News::class)
            ->findBy(['status' => PostStatus::Published], ['createdAt' => 'DESC']);

        foreach ($news as $article) {
            $urls[] = [
                'url' => $this->generateNewsUrl($article),
                'lastmod' => $article->getUpdatedAt() ? $article->getUpdatedAt()->format('Y-m-d') : $article->getCreatedAt()->format('Y-m-d'),
                'changefreq' => 'weekly',
                'priority' => '0.9'
            ];
        }

        $urls[] = [
            'url' => $this->router->generate('caculator_cost_construction', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'lastmod' => (new \DateTime())->format('Y-m-d'),
            'changefreq' => 'monthly',
            'priority' => '0.5'
        ];

        $urls[] = [
            'url' => $this->router->generate('contact', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'lastmod' => (new \DateTime())->format('Y-m-d'),
            'changefreq' => 'monthly',
            'priority' => '0.5'
        ];

        return $urls;
    }

    /**
     * Generate URL for a category page
     *
     * @param NewsCategory $category
     * @return string
     */
    private function generateCategoryUrl(NewsCategory $category)
    {
        // Check if category has a parent
        if ($category->getParentcat() !== 'root') {
            // If it has parent, use two-level route
            return $this->generateBaseUrl() . $category->getParentcat()->getUrl() . '/' . $category->getUrl() . '/';
        } else {
            // Single level category
            return $this->generateBaseUrl() . $category->getUrl() . '/';
        }
    }

    /**
     * Generate URL for a news article
     *
     * @param News $article
     * @return string
     */
    private function generateNewsUrl(News $article)
    {
        // Build URL based on category structure
        $categories = $article->getCategory();

        /* if ($categories && count($categories) > 0) {
            $primaryCategory = $categories[0];
            
            if ($primaryCategory->getParentcat()) {
                // Two-level category
                return $this->generateBaseUrl() . 
                    $primaryCategory->getParentcat()->getUrl() . '/' . 
                    $primaryCategory->getUrl() . '/' . 
                    $article->getUrl();
            } else {
                // Single-level category
                return $this->generateBaseUrl() . $primaryCategory->getUrl() . '/' . $article->getUrl();
            }
        } */

        // Default fallback
        return $this->generateBaseUrl() . $article->getUrl() . '/';
    }

    /**
     * Generate base URL
     *
     * @return string
     */
    private function generateBaseUrl()
    {
        return $this->router->generate('homepage', [], UrlGeneratorInterface::ABSOLUTE_URL);
    }
}
