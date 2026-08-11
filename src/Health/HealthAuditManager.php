<?php

namespace App\Health;

use App\Entity\News;
use App\Entity\NewsCategory;
use App\Entity\Tag;
use App\Enum\PostStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\RouterInterface;

class HealthAuditManager
{
    const MAX_BROKEN_LINKS = 200;
    const MAX_MISSING_IMAGES = 200;
    const MAX_POSTS_WITHOUT_IMAGE = 200;
    const MAX_OUTDATED_POSTS = 200;

    private $em;
    private $router;
    private $kernel;
    private $webRoot;
    private $publishedContentCache;
    private $localFileCache = [];
    private $postUrlCache = [];
    private $categoryUrlCache = [];
    private $tagUrlCache = [];

    public function __construct(EntityManagerInterface $em, RouterInterface $router, KernelInterface $kernel)
    {
        $this->em = $em;
        $this->router = $router;
        $this->kernel = $kernel;
        $this->webRoot = realpath($kernel->getProjectDir() . '/public');
    }

    public function buildReport()
    {
        $brokenLinks = $this->findBrokenInternalLinks();
        $missingImages = $this->findMissingImages();
        $postsWithoutImage = $this->findPostsWithoutFeaturedImage();
        $outdatedPosts = $this->findOutdatedData();
        $uploadUsage = $this->getUploadUsage();

        return [
            'generatedAt' => new \DateTime(),
            'summary' => [
                'brokenLinks' => count($brokenLinks),
                'missingImages' => count($missingImages),
                'postsWithoutImage' => count($postsWithoutImage),
                'outdatedPosts' => count($outdatedPosts),
                'schemaStatus' => 'Đã tự tạo bằng Schema Builder',
                'mailStatus' => 'Tạm bỏ qua',
                'uploadSize' => $uploadUsage['totalHuman'],
                'uploadBytes' => $uploadUsage['totalBytes'],
            ],
            'brokenLinks' => $brokenLinks,
            'missingImages' => $missingImages,
            'postsWithoutImage' => $postsWithoutImage,
            'outdatedPosts' => $outdatedPosts,
            'uploadUsage' => $uploadUsage,
            'skipped' => [
                'schema' => 'Schema cho site, bài viết/page và danh mục được tạo tự động; field JSON-LD override vẫn được giữ cho trường hợp đặc biệt.',
                'mail' => 'Form lỗi gửi mail đang tạm bỏ qua theo yêu cầu.',
            ],
        ];
    }

    private function findBrokenInternalLinks()
    {
        $issues = [];
        $seen = [];
        $posts = $this->getPublishedContent();

        foreach ($posts as $post) {
            $links = $this->extractAttributes((string) $post->getContents(), 'a', 'href');

            foreach ($links as $href) {
                $path = $this->normalizeLocalUrl($href);

                if (!$path || isset($seen[$post->getId() . ':' . $path])) {
                    continue;
                }

                $seen[$post->getId() . ':' . $path] = true;

                if (!$this->isInternalPathHealthy($path)) {
                    $issues[] = [
                        'post' => $post,
                        'url' => $href,
                        'path' => $path,
                        'reason' => 'Không tìm thấy route, bài viết published hoặc file phù hợp.',
                    ];
                }

                if (count($issues) >= self::MAX_BROKEN_LINKS) {
                    return $issues;
                }
            }
        }

        return $issues;
    }

    private function findMissingImages()
    {
        $issues = [];

        foreach ($this->findMissingFeaturedImages() as $issue) {
            $issues[] = $issue;
        }

        foreach ($this->findMissingContentImages() as $issue) {
            $issues[] = $issue;
        }

        return array_slice($issues, 0, self::MAX_MISSING_IMAGES);
    }

    private function findPostsWithoutFeaturedImage()
    {
        return $this->em->getRepository(News::class)->createQueryBuilder('n')
            ->where('n.status = :status')
            ->andWhere('n.postType IN (:postTypes)')
            ->andWhere('n.images IS NULL OR n.images = :empty')
            ->setParameter('status', PostStatus::Published)
            ->setParameter('postTypes', ['post', 'page'])
            ->setParameter('empty', '')
            ->orderBy('n.updatedAt', 'DESC')
            ->setMaxResults(self::MAX_POSTS_WITHOUT_IMAGE)
            ->getQuery()
            ->getResult();
    }

    private function findMissingFeaturedImages()
    {
        $issues = [];
        $posts = $this->em->getRepository(News::class)->createQueryBuilder('n')
            ->where('n.images IS NOT NULL')
            ->andWhere('n.images != :empty')
            ->setParameter('empty', '')
            ->getQuery()
            ->getResult();

        foreach ($posts as $post) {
            // getImages() lưu URL đầy đủ tính từ webroot (Media picker), không
            // còn là filename trần cần ghép tiền tố như hồi còn VichUploaderBundle.
            $path = ltrim($post->getImages(), '/');

            if (!$this->localFileExists($path)) {
                $issues[] = [
                    'source' => 'Bài viết/Page',
                    'title' => $post->getTitle(),
                    'path' => '/' . $path,
                    'editUrl' => $post->isPage()
                        ? $this->router->generate('admin_page_edit', ['id' => $post->getId()])
                        : $this->router->generate('admin_news_edit', ['id' => $post->getId()]),
                ];
            }
        }

        return $issues;
    }

    private function findMissingContentImages()
    {
        $issues = [];
        $seen = [];

        foreach ($this->getPublishedContent() as $post) {
            $images = $this->extractAttributes((string) $post->getContents(), 'img', 'src');

            foreach ($images as $src) {
                $path = $this->normalizeLocalUrl($src);

                if (!$path || isset($seen[$post->getId() . ':' . $path])) {
                    continue;
                }

                $seen[$post->getId() . ':' . $path] = true;

                if (!$this->localFileExists($path)) {
                    $issues[] = [
                        'source' => 'Ảnh trong nội dung',
                        'title' => $post->getTitle(),
                        'path' => $path,
                        'editUrl' => $post->isPage()
                            ? $this->router->generate('admin_page_edit', ['id' => $post->getId()])
                            : $this->router->generate('admin_news_edit', ['id' => $post->getId()]),
                    ];
                }

                if (count($issues) >= self::MAX_MISSING_IMAGES) {
                    return $issues;
                }
            }
        }

        return $issues;
    }

    private function getUploadUsage()
    {
        $paths = [
            'Ảnh bài viết (featured)' => 'uploads/images/news',
            'Ảnh danh mục' => 'uploads/images/newscategory',
            'CKFinder uploads' => 'uploads/ckfinder',
            'Banner' => 'uploads/images/banner',
        ];
        $items = [];
        $total = 0;

        foreach ($paths as $label => $path) {
            $bytes = $this->getDirectorySize($this->webRoot . '/' . $path);
            $total += $bytes;
            $items[] = [
                'label' => $label,
                'path' => '/' . $path,
                'bytes' => $bytes,
                'human' => $this->formatBytes($bytes),
            ];
        }

        return [
            'items' => $items,
            'totalBytes' => $total,
            'totalHuman' => $this->formatBytes($total),
        ];
    }

    private function getPublishedContent()
    {
        if ($this->publishedContentCache !== null) {
            return $this->publishedContentCache;
        }

        $this->publishedContentCache = $this->em->getRepository(News::class)->createQueryBuilder('n')
            ->where('n.status = :status')
            ->andWhere('n.postType IN (:postTypes)')
            ->setParameter('status', PostStatus::Published)
            ->setParameter('postTypes', ['post', 'page'])
            ->orderBy('n.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->publishedContentCache;
    }



    private function extractAttributes($html, $tag, $attribute)
    {
        if (trim($html) === '') {
            return [];
        }

        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();

        $values = [];

        foreach ($dom->getElementsByTagName($tag) as $node) {
            if ($node->hasAttribute($attribute)) {
                $values[] = trim($node->getAttribute($attribute));
            }
        }

        return array_values(array_filter($values));
    }

    private function normalizeLocalUrl($url)
    {
        $url = trim((string) $url);

        if ($url === '' || $url[0] === '#' || preg_match('/^(mailto:|tel:|javascript:|data:)/i', $url)) {
            return null;
        }

        $parts = @parse_url($url);

        if (!$parts || !empty($parts['host'])) {
            $host = isset($parts['host']) ? strtolower($parts['host']) : '';
            $requestHost = isset($_SERVER['HTTP_HOST']) ? strtolower($_SERVER['HTTP_HOST']) : '';

            if ($host && $requestHost && $host !== $requestHost) {
                return null;
            }
        }

        $path = isset($parts['path']) ? $parts['path'] : $url;

        if ($path === '') {
            return null;
        }

        return '/' . ltrim(rawurldecode($path), '/');
    }

    private function isInternalPathHealthy($path)
    {
        if ($this->localFileExists($path)) {
            return true;
        }

        if (preg_match('#/([^/]+)\.html$#', $path, $matches)) {
            return $this->publishedPostExists($matches[1]);
        }

        try {
            $route = $this->router->match($path);

            if (!$this->isMatchedFrontRouteHealthy($route)) {
                return false;
            }

            return true;
        } catch (ResourceNotFoundException $e) {
            return false;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function isMatchedFrontRouteHealthy(array $route)
    {
        if (empty($route['_route'])) {
            return true;
        }

        switch ($route['_route']) {
            case 'dynamic_post_page':
                $slug = $route['slug'] ?? ($route['level1'] ?? '');
                if (empty($slug)) return false;
                return $this->publishedPostExists($slug) || $this->categoryExists($slug);

            case 'dynamic_category_post':
            case 'list_category':
                $level1 = $route['level1'] ?? ($route['slug'] ?? '');
                $level2 = $route['level2'] ?? '';
                if (empty($level1) || empty($level2)) return false;

                $parent = $this->findCategoryByUrl($level1);
                if (!$parent) return false;

                $childCat = $this->findCategoryByUrl($level2);
                if ($childCat && is_object($childCat->getParentcat()) && $childCat->getParentcat()->getId() === $parent->getId()) {
                    return true;
                }

                if ($this->publishedPostExists($level2)) {
                    return true;
                }

                return false;

            case 'dynamic_category_level2_post':
                $level1 = $route['level1'] ?? '';
                $level2 = $route['level2'] ?? '';
                $level3 = $route['level3'] ?? '';
                if (empty($level1) || empty($level2) || empty($level3)) return false;

                $childCat = $this->findCategoryByUrl($level2);
                if (!$childCat) return false;

                $grandChildCat = $this->findCategoryByUrl($level3);
                if ($grandChildCat && is_object($grandChildCat->getParentcat()) && $grandChildCat->getParentcat()->getId() === $childCat->getId()) {
                    return true;
                }

                if ($this->publishedPostExists($level3)) {
                    return true;
                }

                return false;

            case 'news_category':
                if (empty($route['level1'])) return false;
                return $this->categoryExists($route['level1']);

            case 'tags':
                if (empty($route['slug'])) return false;
                return $this->tagExists($route['slug']);

            default:
                return true;
        }
    }

    private function localFileExists($path)
    {
        if (!$this->webRoot || !$path) {
            return false;
        }

        $relative = ltrim(parse_url($path, PHP_URL_PATH) ?: $path, '/');
        $absolute = $this->webRoot . '/' . $relative;

        if (!array_key_exists($absolute, $this->localFileCache)) {
            $this->localFileCache[$absolute] = is_file($absolute);
        }

        return $this->localFileCache[$absolute];
    }

    private function publishedPostExists($url)
    {
        $url = (string) $url;

        if (!array_key_exists($url, $this->postUrlCache)) {
            $this->postUrlCache[$url] = $this->em->getRepository(News::class)->findOneBy([
                'url' => $url,
                'status' => PostStatus::Published,
            ]) !== null;
        }

        return $this->postUrlCache[$url];
    }

    private function categoryExists($url)
    {
        return $this->findCategoryByUrl($url) !== null;
    }

    private function findCategoryByUrl($url)
    {
        $url = (string) $url;

        if (!array_key_exists($url, $this->categoryUrlCache)) {
            $this->categoryUrlCache[$url] = $this->em->getRepository(NewsCategory::class)->findOneBy([
                'url' => $url,
                'enable' => true,
            ]);
        }

        return $this->categoryUrlCache[$url];
    }

    private function tagExists($url)
    {
        $url = (string) $url;

        if (!array_key_exists($url, $this->tagUrlCache)) {
            $this->tagUrlCache[$url] = $this->em->getRepository(Tag::class)->findOneBy([
                'url' => $url,
            ]) !== null;
        }

        return $this->tagUrlCache[$url];
    }

    private function getDirectorySize($path)
    {
        if (!is_dir($path)) {
            return 0;
        }

        $size = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }

        return $size;
    }

    private function formatBytes($bytes)
    {
        $bytes = (float) $bytes;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $index = 0;

        while ($bytes >= 1024 && $index < count($units) - 1) {
            $bytes /= 1024;
            $index++;
        }

        return number_format($bytes, $index === 0 ? 0 : 2) . ' ' . $units[$index];
    }

    private function findOutdatedData()
    {
        $issues = [];
        $currentYear = (int) date('Y');
        $posts = $this->getPublishedContent();

        foreach ($posts as $post) {
            $notes = (string) $post->getNote();
            
            // Check if we should ignore ALL outdated years
            if (stripos($notes, '[ignore-outdated]') !== false || stripos($notes, '[ignore-outdated:all]') !== false) {
                continue;
            }

            // Check if we should ignore specific years
            $ignoredYears = [];
            if (preg_match('/\[ignore-outdated:([\d,]+)\]/i', $notes, $matches)) {
                $ignoredYears = array_map('intval', explode(',', $matches[1]));
            }

            $matchedYears = [];

            $isOutdatedYear = function ($yr) use ($currentYear, $ignoredYears) {
                $yrInt = (int)$yr;
                return $yrInt < $currentYear && $yrInt >= 2015 && !in_array($yrInt, $ignoredYears, true);
            };

            // Check Title
            if (preg_match_all('/\b(20\d{2})\b/', $post->getTitle(), $matches)) {
                foreach ($matches[1] as $yr) {
                    if ($isOutdatedYear($yr)) {
                        $matchedYears[(int)$yr]['title'] = true;
                    }
                }
            }

            // Check Content
            if (preg_match_all('/\b(20\d{2})\b/', strip_tags((string) $post->getContents()), $matches)) {
                foreach ($matches[1] as $yr) {
                    if ($isOutdatedYear($yr)) {
                        $matchedYears[(int)$yr]['contents'] = true;
                    }
                }
            }

            // Check Description
            if (preg_match_all('/\b(20\d{2})\b/', (string) $post->getDescription(), $matches)) {
                foreach ($matches[1] as $yr) {
                    if ($isOutdatedYear($yr)) {
                        $matchedYears[(int)$yr]['description'] = true;
                    }
                }
            }

            // Check Page Title / Meta Title
            if ($post->getPageTitle() && preg_match_all('/\b(20\d{2})\b/', $post->getPageTitle(), $matches)) {
                foreach ($matches[1] as $yr) {
                    if ($isOutdatedYear($yr)) {
                        $matchedYears[(int)$yr]['pageTitle'] = true;
                    }
                }
            }

            // Check Page Description / Meta Description
            if ($post->getPageDescription() && preg_match_all('/\b(20\d{2})\b/', $post->getPageDescription(), $matches)) {
                foreach ($matches[1] as $yr) {
                    if ($isOutdatedYear($yr)) {
                        $matchedYears[(int)$yr]['pageDescription'] = true;
                    }
                }
            }

            if (!empty($matchedYears)) {
                $reasons = [];
                foreach ($matchedYears as $yr => $fields) {
                    $fieldNames = [];
                    if (isset($fields['title'])) $fieldNames[] = 'Tiêu đề';
                    if (isset($fields['contents'])) $fieldNames[] = 'Nội dung';
                    if (isset($fields['description'])) $fieldNames[] = 'Mô tả';
                    if (isset($fields['pageTitle'])) $fieldNames[] = 'SEO Tiêu đề';
                    if (isset($fields['pageDescription'])) $fieldNames[] = 'SEO Mô tả';

                    $reasons[] = sprintf("Năm %d trong (%s)", $yr, implode(', ', $fieldNames));
                }

                $issues[] = [
                    'post' => $post,
                    'reason' => implode('; ', $reasons),
                    'editUrl' => $post->isPage()
                        ? $this->router->generate('admin_page_edit', ['id' => $post->getId()])
                        : $this->router->generate('admin_news_edit', ['id' => $post->getId()]),
                ];

                if (count($issues) >= self::MAX_OUTDATED_POSTS) {
                    return $issues;
                }
            }
        }

        return $issues;
    }
}
