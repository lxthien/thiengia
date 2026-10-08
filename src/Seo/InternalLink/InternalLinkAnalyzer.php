<?php

namespace App\Seo\InternalLink;

use App\Entity\Menu;
use App\Entity\MenuItem;
use App\Entity\News;
use App\Entity\NewsCategory;
use App\Entity\Redirect;
use App\Enum\PostStatus;
use App\Health\InternalUrlNormalizer;
use App\Service\ContentMetrics;
use App\Service\MenuService;
use App\Service\SettingsManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * Dựng đồ thị link nội bộ của site (bài viết, trang, danh mục, menu) và chấm
 * theo hướng dẫn "SEO link best practices" của Google:
 *  - mọi trang quan trọng phải có ít nhất 1 link từ trang khác (trang mồ côi);
 *  - link phải crawl được (<a href> trỏ tới URL thật);
 *  - anchor text mô tả, ngắn gọn, không chung chung, không rỗng;
 *  - không đặt nhiều link sát nhau, không nhồi quá nhiều link.
 * Các kiểm tra còn lại (link hỏng, link qua chuyển hướng, URL không chuẩn,
 * nofollow nội bộ, link tới trang noindex, độ sâu click, trang cụt) là thông
 * lệ kỹ thuật để Googlebot đi hết site mà không phí lượt crawl.
 *
 * Chạy trong app:compute-internal-links (cron) hoặc nút "Phân tích lại";
 * dashboard chỉ đọc kết quả đã lưu ở InternalLinkReport.
 */
class InternalLinkAnalyzer
{
    public const VERSION = 1;

    // Ngưỡng nội bộ, không phải con số do Google công bố.
    public const MAX_CLICK_DEPTH = 3;
    public const LONG_ANCHOR_WORDS = 12;
    public const MAX_CONTENT_LINKS = 100;
    public const MIN_WORDS_PER_LINK = 40;
    private const MAX_OPPORTUNITIES = 300;
    private const MAX_OPPORTUNITIES_PER_TARGET = 10;

    private const GENERIC_ANCHORS = [
        'tại đây', 'ở đây', 'đây', 'vào đây', 'xem tại đây', 'bấm vào đây', 'nhấn vào đây', 'click vào đây',
        'xem thêm', 'đọc thêm', 'tìm hiểu thêm', 'xem chi tiết', 'chi tiết', 'xem ngay', 'xem tiếp', 'đọc tiếp',
        'link', 'liên kết', 'trang này', 'bài viết này', 'bài này', 'website',
        'click here', 'click', 'here', 'read more', 'learn more', 'more', 'this page', 'this',
    ];

    private array $pages = [];
    private array $links = [];
    /** @var array<string, array<string, int>> đồ thị có trọng số (số click) dùng tính độ sâu */
    private array $edges = [];
    private array $newsBySlug = [];
    private array $unpublishedSlugs = [];
    private array $categoriesBySlug = [];
    private array $redirects = [];
    private array $ownHosts = [];
    private string $webRoot;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RouterInterface $router,
        private readonly MenuService $menuService,
        private readonly ContentMetrics $contentMetrics,
        private readonly SettingsManager $settings,
        string $projectDir,
        private readonly string $siteUrl = '',
    ) {
        $this->webRoot = $projectDir . '/public';
    }

    public function analyze(): array
    {
        $this->pages = $this->links = $this->edges = [];
        $this->newsBySlug = $this->unpublishedSlugs = $this->categoriesBySlug = [];
        $this->ownHosts = $this->resolveOwnHosts();
        $this->redirects = $this->em->createQueryBuilder()
            ->select('r.sourceUrl', 'r.destinationUrl', 'r.matchType')
            ->from(Redirect::class, 'r')
            ->where('r.isActive = true')
            ->orderBy('r.orderNum', 'ASC')
            ->addOrderBy('r.id', 'DESC')
            ->getQuery()
            ->getArrayResult();

        $contents = $this->loadPages();
        $this->addStructuralLinks();
        $this->addMenuLinks();

        $linkableText = [];
        foreach ($contents as $key => $html) {
            $linkableText[$key] = $this->extractContentLinks($key, $html);
        }

        $this->computeMetrics();
        $opportunities = $this->findOpportunities($linkableText);
        $summary = $this->summarize($opportunities);

        return [
            'version' => self::VERSION,
            'summary' => $summary,
            'score' => $this->score($summary),
            'pages' => $this->pages,
            'links' => $this->links,
            'opportunities' => $opportunities,
        ];
    }

    /**
     * @return array<string, string> key trang => HTML nội dung cần quét link
     */
    private function loadPages(): array
    {
        $contents = [];
        $this->addPage('home', 'home', 'Trang chủ', '/', true);

        $categories = $this->em->getRepository(NewsCategory::class)->findBy(['enable' => true]);
        foreach ($categories as $category) {
            // getParentcat() của danh mục gốc có thể không phải object (dữ liệu cũ).
            $parent = is_object($category->getParentcat()) ? $category->getParentcat() : null;
            $path = '/' . ($parent ? $parent->getUrl() . '/' : '') . $category->getUrl() . '/';
            $key = 'cat:' . $category->getId();

            $this->addPage($key, 'category', (string) $category->getName(), $path, (bool) $category->isMetaIndex(), [
                'edit' => ['route' => 'admin_newscategory_edit', 'id' => $category->getId()],
                'keywords' => self::splitKeywords($category->getPageKeyword()),
                'parent' => $parent ? 'cat:' . $parent->getId() : null,
            ]);
            $this->categoriesBySlug[$category->getUrl()] = ['key' => $key, 'parentSlug' => $parent ? $parent->getUrl() : null];
            $contents[$key] = (string) $category->getContent();
        }

        $rows = $this->em->createQueryBuilder()
            ->select('n.id', 'n.title', 'n.url', 'n.postType', 'n.status', 'n.contents', 'n.pageKeyword', 'n.metaIndex', 'n.pageBuilderEnabled', 'n.pageBuilderData', 'IDENTITY(n.parent) AS parentId')
            ->from(News::class, 'n')
            ->where('n.postType IN (:postTypes)')
            ->setParameter('postTypes', ['post', 'page'])
            ->orderBy('n.createdAt', 'DESC')
            ->getQuery()
            ->getArrayResult();

        foreach ($rows as $row) {
            $status = $row['status'] instanceof PostStatus ? $row['status'] : PostStatus::tryFrom((string) $row['status']);
            if ($status !== PostStatus::Published) {
                $this->unpublishedSlugs[$row['url']] = true;
                continue;
            }

            $isPage = $row['postType'] === 'page';
            $key = 'news:' . $row['id'];
            $html = (string) $row['contents'];
            if ($row['pageBuilderEnabled'] && $row['pageBuilderData']) {
                $html .= self::collectHtml(json_decode((string) $row['pageBuilderData'], true));
            }

            $this->addPage($key, $isPage ? 'page' : 'post', (string) $row['title'], '/' . $row['url'] . '/', (bool) $row['metaIndex'], [
                'edit' => ['route' => $isPage ? 'admin_page_edit' : 'admin_news_edit', 'id' => $row['id']],
                'keywords' => self::splitKeywords($row['pageKeyword']),
                'parent' => $row['parentId'] ? 'news:' . $row['parentId'] : null,
                'wordCount' => $this->contentMetrics->countWords($html),
            ]);
            $this->newsBySlug[$row['url']] = $key;
            $contents[$key] = $html;
        }

        return $contents;
    }

    private function addPage(string $key, string $type, string $title, string $path, bool $indexable, array $extra = []): void
    {
        $this->pages[$key] = $extra + [
            'key' => $key,
            'type' => $type,
            'title' => $title,
            'path' => $path,
            'indexable' => $indexable,
            'edit' => null,
            'keywords' => [],
            'parent' => null,
            'wordCount' => 0,
            'inContent' => 0,
            'inContentLinks' => 0,
            'inMenu' => false,
            'inStructure' => 0,
            'outInternal' => 0,
            'outExternal' => 0,
            'depth' => null,
            'issues' => [],
        ];
    }

    /**
     * Link do template tự sinh, không nằm trong nội dung: danh mục liệt kê
     * bài viết (có phân trang), breadcrumb bài → danh mục, trang con → trang
     * cha, danh mục con ↔ danh mục cha.
     */
    private function addStructuralLinks(): void
    {
        $perPage = max(1, (int) ($this->settings->get('numberRecordOnPage') ?: 10));

        foreach ($this->pages as $key => $page) {
            if ($page['parent'] && isset($this->pages[$page['parent']])) {
                $this->addStructure($key, $page['parent']);
                if ($page['type'] === 'category') {
                    $this->addStructure($page['parent'], $key);
                }
            }
        }

        $pairs = $this->em->createQueryBuilder()
            ->select('n.id AS newsId', 'c.id AS categoryId')
            ->from(News::class, 'n')
            ->innerJoin('n.category', 'c')
            ->where('n.status = :status')
            ->setParameter('status', PostStatus::Published)
            ->orderBy('n.createdAt', 'DESC')
            ->getQuery()
            ->getArrayResult();

        $position = [];
        foreach ($pairs as $pair) {
            $news = 'news:' . $pair['newsId'];
            $category = 'cat:' . $pair['categoryId'];
            if (!isset($this->pages[$news], $this->pages[$category])) {
                continue;
            }

            // Bài nằm ở trang 2, 3... của danh mục thì cần thêm click để tới.
            $position[$category] = ($position[$category] ?? -1) + 1;
            $this->addStructure($category, $news, 1 + intdiv($position[$category], $perPage));
            $this->addStructure($news, $category);
        }
    }

    private function addStructure(string $from, string $to, int $clicks = 1): void
    {
        if (isset($this->edges[$from][$to])) {
            $this->edges[$from][$to] = min($this->edges[$from][$to], $clicks);

            return;
        }

        $this->edges[$from][$to] = $clicks;
        $this->pages[$to]['inStructure']++;
    }

    private function addMenuLinks(): void
    {
        $items = $this->em->createQueryBuilder()
            ->select('i', 'm')
            ->from(MenuItem::class, 'i')
            ->innerJoin('i.menu', 'm')
            ->where('i.enable = true')
            ->andWhere('m.enable = true')
            ->orderBy('i.position', 'ASC')
            ->getQuery()
            ->getResult();

        foreach ($items as $item) {
            $href = (string) $this->menuService->getItemUrl($item);
            if ($href === '' || $href === '#') {
                continue;
            }

            $link = $this->classify($href) + [
                'source' => 'menu',
                'kind' => 'menu',
                'href' => $href,
                'anchor' => (string) $item->getTitle(),
                'flags' => [],
                'menuId' => $item->getMenu() instanceof Menu ? $item->getMenu()->getId() : null,
            ];
            $this->links[] = $link;

            if ($link['target'] !== null) {
                $this->pages[$link['target']]['inMenu'] = true;
                // Menu có ở mọi trang: tính từ trang chủ là 1 click.
                $this->edges['home'][$link['target']] = 1;
            }
        }
    }

    /**
     * Quét mọi thẻ <a> trong nội dung của 1 trang.
     *
     * @return string phần chữ KHÔNG nằm trong link (để tìm cơ hội đặt link)
     */
    private function extractContentLinks(string $sourceKey, string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML('<?xml encoding="UTF-8"><div>' . $html . '</div>');
        libxml_clear_errors();

        $sources = [];
        $anchors = iterator_to_array($dom->getElementsByTagName('a'));

        foreach ($anchors as $node) {
            $href = trim($node->getAttribute('href'));
            $anchor = self::normalizeText($node->textContent);
            $flags = [];

            if ($anchor === '') {
                foreach ($node->getElementsByTagName('img') as $img) {
                    $anchor = self::normalizeText($img->getAttribute('alt'));
                    break;
                }
            }

            if ($href === '') {
                // <a name="..."> / <a id="..."> là điểm neo trong trang, không phải link.
                if (!$node->hasAttribute('onclick') && !$node->hasAttribute('href')) {
                    continue;
                }
                $link = ['status' => 'uncrawlable', 'target' => null, 'path' => null, 'note' => 'Thẻ <a> không có href trỏ tới URL thật.'];
            } elseif (preg_match('#^\s*javascript:#i', $href)) {
                $link = ['status' => 'uncrawlable', 'target' => null, 'path' => null, 'note' => 'href dùng javascript:, Google không đi theo được.'];
            } elseif ($href[0] === '#' || preg_match('#^(mailto|tel|sms|zalo|viber|skype):#i', $href)) {
                continue;
            } else {
                $link = $this->classify($href);
            }

            if ($link['status'] === 'file') {
                continue;
            }

            if ($link['status'] === 'external') {
                $this->pages[$sourceKey]['outExternal']++;
            } else {
                $this->pages[$sourceKey]['outInternal']++;
                $rel = preg_split('/\s+/', strtolower($node->getAttribute('rel')), -1, PREG_SPLIT_NO_EMPTY);
                if (in_array('nofollow', $rel, true)) {
                    $flags[] = 'nofollow';
                }

                if ($anchor === '') {
                    $flags[] = 'empty';
                } elseif (in_array(mb_strtolower(trim($anchor, " \t.,:;!?»›→>")), self::GENERIC_ANCHORS, true)) {
                    $flags[] = 'generic';
                } elseif (count(preg_split('/\s+/u', $anchor)) > self::LONG_ANCHOR_WORDS) {
                    $flags[] = 'long';
                }

                if (self::isChained($node)) {
                    $flags[] = 'adjacent';
                }

                if ($link['target'] !== null) {
                    if ($link['target'] === $sourceKey) {
                        $flags[] = 'self';
                    } else {
                        if (!$this->pages[$link['target']]['indexable']) {
                            $flags[] = 'noindex_target';
                        }
                        $this->pages[$link['target']]['inContentLinks']++;
                        $sources[$link['target']] = true;
                        if (!in_array('nofollow', $flags, true)) {
                            $this->edges[$sourceKey][$link['target']] = 1;
                        }
                    }
                }
            }

            $this->links[] = $link + [
                'source' => $sourceKey,
                'kind' => 'content',
                'href' => $href,
                'anchor' => mb_substr($anchor, 0, 300),
                'flags' => $flags,
            ];
        }

        foreach (array_keys($sources) as $target) {
            $this->pages[$target]['inContent']++;
        }

        // Bỏ link và tiêu đề khỏi DOM, phần chữ còn lại là nơi có thể đặt link mới.
        foreach (['a', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'script', 'style'] as $tag) {
            foreach (iterator_to_array($dom->getElementsByTagName($tag)) as $node) {
                $node->parentNode?->removeChild($node);
            }
        }

        // Ghép từng đoạn chữ bằng khoảng trắng để chữ của 2 khối liền nhau không dính vào nhau.
        $texts = [];
        foreach ((new \DOMXPath($dom))->query('//text()') as $text) {
            $texts[] = $text->nodeValue;
        }

        return self::normalizeText(implode(' ', $texts));
    }

    /**
     * Phân loại 1 href: ngoài site, file, hỏng, chuyển hướng, URL không chuẩn hay hợp lệ.
     *
     * @return array{status: string, target: ?string, path: ?string, note: string}
     */
    private function classify(string $href): array
    {
        $path = null;
        foreach ($this->ownHosts as $host) {
            $path = InternalUrlNormalizer::normalize($href, $host);
            if ($path !== null) {
                break;
            }
        }

        if ($path === null) {
            return ['status' => 'external', 'target' => null, 'path' => null, 'note' => ''];
        }

        if (is_file($this->webRoot . $path)) {
            return ['status' => 'file', 'target' => null, 'path' => $path, 'note' => ''];
        }

        $redirected = $this->followRedirects($path);
        if ($redirected !== null) {
            $destination = $this->resolvePath($redirected);

            return [
                'status' => $destination['status'] === 'broken' ? 'broken' : 'redirect',
                'target' => $destination['target'],
                'path' => $path,
                'note' => $destination['status'] === 'broken'
                    ? sprintf('Chuyển hướng tới %s nhưng đích không tồn tại.', $redirected)
                    : sprintf('Đi qua chuyển hướng tới %s. Nên sửa link trỏ thẳng tới đích.', $redirected),
            ];
        }

        return $this->resolvePath($path) + ['path' => $path];
    }

    /**
     * @return array{status: string, target: ?string, note: string}
     */
    private function resolvePath(string $path): array
    {
        $ok = static fn (?string $target, string $note = '') => ['status' => 'ok', 'target' => $target, 'note' => $note];
        $broken = static fn (string $note) => ['status' => 'broken', 'target' => null, 'note' => $note];

        if ($path === '/') {
            return $ok('home');
        }

        $segments = array_values(array_filter(explode('/', $path), 'strlen'));
        $last = end($segments);
        $missingSlash = !str_ends_with($path, '/');

        $result = null;
        if (count($segments) === 1) {
            $result = $this->resolveSlug($last, null);
        } elseif (count($segments) <= 3 && isset($this->categoriesBySlug[$segments[0]])) {
            if (ctype_digit($last)) {
                // /danh-muc/2 là trang phân trang của danh mục.
                $category = $this->categoriesBySlug[$segments[count($segments) - 2]] ?? null;
                if ($category) {
                    return ['status' => 'noncanonical', 'target' => $category['key'], 'note' => 'Link tới trang phân trang của danh mục, nên trỏ về trang đầu.'];
                }
            }
            $result = $this->resolveSlug($last, $segments[count($segments) - 2]);
        }

        if ($result === null) {
            $result = $this->resolveStaticRoute($path, $missingSlash);
        }

        // Route tĩnh khớp đúng nguyên văn (vd /tag/abc) thì không bị chuyển hướng.
        if ($result['status'] === 'ok' && $missingSlash && $result['target'] !== 'route:' . $path) {
            return ['status' => 'redirect', 'target' => $result['target'], 'note' => 'Thiếu dấu "/" cuối URL nên bị chuyển hướng 301 thêm một lần.'];
        }

        return $result;
    }

    private function resolveSlug(string $slug, ?string $parentSlug): array
    {
        if (isset($this->newsBySlug[$slug])) {
            $target = $this->newsBySlug[$slug];
            if ($parentSlug !== null) {
                return ['status' => 'noncanonical', 'target' => $target, 'note' => sprintf('Trang vẫn mở được nhưng URL chuẩn (canonical) là %s.', $this->pages[$target]['path'])];
            }

            return ['status' => 'ok', 'target' => $target, 'note' => ''];
        }

        if (isset($this->categoriesBySlug[$slug])) {
            $category = $this->categoriesBySlug[$slug];
            if ($category['parentSlug'] === $parentSlug) {
                return ['status' => 'ok', 'target' => $category['key'], 'note' => ''];
            }
            if ($parentSlug === null) {
                return ['status' => 'redirect', 'target' => $category['key'], 'note' => sprintf('Danh mục con: bị chuyển hướng 301 tới %s.', $this->pages[$category['key']]['path'])];
            }
        }

        if (isset($this->unpublishedSlugs[$slug])) {
            return ['status' => 'broken', 'target' => null, 'note' => 'Bài viết/trang đích chưa xuất bản hoặc đã lưu trữ (404).'];
        }

        if ($parentSlug !== null) {
            return ['status' => 'broken', 'target' => null, 'note' => 'Không tìm thấy bài viết hoặc danh mục khớp với URL này (404).'];
        }

        return $this->resolveStaticRoute('/' . $slug . '/', false, 'Không tìm thấy bài viết, trang hoặc danh mục có đường dẫn này (404).');
    }

    /**
     * Trang do code định nghĩa (liên hệ, công cụ tính...) chứ không nằm trong DB nội dung.
     */
    private function resolveStaticRoute(string $path, bool $missingSlash, string $brokenNote = 'URL không khớp với trang nào của site (404).'): array
    {
        foreach ($missingSlash ? [$path, $path . '/'] : [$path] as $candidate) {
            try {
                $route = $this->router->match($candidate);
            } catch (\Throwable) {
                continue;
            }

            $name = (string) ($route['_route'] ?? '');
            if ($name === '' || str_starts_with($name, 'dynamic_') || in_array($name, ['news_category', 'list_category'], true)) {
                continue;
            }

            $key = 'route:' . $candidate;
            if (!isset($this->pages[$key])) {
                // Trang thẻ (tag) đang đặt noindex trong template.
                $this->addPage($key, 'route', $candidate, $candidate, $name !== 'tags' && $name !== 'news_search');
            }

            if ($candidate !== $path) {
                return ['status' => 'redirect', 'target' => $key, 'note' => 'Thiếu dấu "/" cuối URL nên bị chuyển hướng 301 thêm một lần.'];
            }

            return ['status' => 'ok', 'target' => $key, 'note' => ''];
        }

        return ['status' => 'broken', 'target' => null, 'note' => $brokenNote];
    }

    /**
     * Áp dụng bảng Chuyển hướng URL giống RedirectSubscriber.
     *
     * @return string|null đường dẫn đích cuối cùng, null nếu không dính chuyển hướng nào
     */
    private function followRedirects(string $path): ?string
    {
        $current = $path;
        $visited = [$path => true];

        for ($hop = 0; $hop < 5; $hop++) {
            $next = null;
            foreach ($this->redirects as $redirect) {
                $source = (string) $redirect['sourceUrl'];
                $matched = match ($redirect['matchType']) {
                    'exact' => $current === $source,
                    'wildcard' => (bool) preg_match('#^' . str_replace('\*', '.*', preg_quote($source, '#')) . '$#i', $current),
                    'regex' => @preg_match(str_starts_with($source, '/') || str_starts_with($source, '#') ? $source : '#' . $source . '#i', $current) === 1,
                    default => false,
                };

                if ($matched) {
                    $next = (string) (parse_url((string) $redirect['destinationUrl'], PHP_URL_PATH) ?: '/');
                    break;
                }
            }

            if ($next === null || isset($visited[$next])) {
                break;
            }

            $visited[$next] = true;
            $current = $next;
        }

        return $current === $path ? null : $current;
    }

    private function computeMetrics(): void
    {
        // Độ sâu click ngắn nhất tính từ trang chủ (Dijkstra, trọng số nhỏ nên duyệt mảng là đủ).
        $depth = ['home' => 0];
        $queue = ['home' => 0];
        while ($queue) {
            asort($queue);
            $current = array_key_first($queue);
            $distance = $queue[$current];
            unset($queue[$current]);

            foreach ($this->edges[$current] ?? [] as $target => $clicks) {
                if (!isset($depth[$target]) || $distance + $clicks < $depth[$target]) {
                    $depth[$target] = $distance + $clicks;
                    $queue[$target] = $distance + $clicks;
                }
            }
        }

        foreach ($this->pages as $key => &$page) {
            $page['depth'] = $depth[$key] ?? null;
            if ($key === 'home') {
                continue;
            }

            $issues = [];
            $isContent = in_array($page['type'], ['post', 'page', 'category'], true);

            if ($page['indexable']) {
                if ($page['inContent'] === 0 && !$page['inMenu'] && $page['inStructure'] === 0) {
                    $issues[] = 'orphan';
                } elseif ($page['inContent'] === 0) {
                    $issues[] = 'no_contextual';
                } elseif ($page['inContent'] === 1 && !$page['inMenu']) {
                    $issues[] = 'single_source';
                }

                if ($page['depth'] === null) {
                    if (!in_array('orphan', $issues, true)) {
                        $issues[] = 'unreachable';
                    }
                } elseif ($page['depth'] > self::MAX_CLICK_DEPTH) {
                    $issues[] = 'deep';
                }
            }

            if ($isContent && $page['type'] !== 'category' && $page['outInternal'] === 0) {
                $issues[] = 'dead_end';
            }

            $totalLinks = $page['outInternal'] + $page['outExternal'];
            if ($totalLinks > self::MAX_CONTENT_LINKS || ($totalLinks >= 10 && $page['wordCount'] > 0 && $page['wordCount'] / $totalLinks < self::MIN_WORDS_PER_LINK)) {
                $issues[] = 'too_many_links';
            }

            $page['issues'] = $issues;
        }
        unset($page);
    }

    /**
     * Trang A nhắc tới từ khóa trọng tâm của trang B trong phần chữ thường mà
     * chưa hề link tới B → gợi ý đặt link ngữ cảnh.
     */
    private function findOpportunities(array $linkableText): array
    {
        $linked = [];
        foreach ($this->links as $link) {
            if ($link['kind'] === 'content' && $link['target'] !== null) {
                $linked[$link['source']][$link['target']] = true;
            }
        }

        $lowerText = array_map('mb_strtolower', array_filter($linkableText));
        $opportunities = [];

        // Ưu tiên trang đang ít link ngữ cảnh nhất.
        $targets = array_filter($this->pages, static fn (array $page) => $page['indexable'] && $page['keywords']);
        uasort($targets, static fn (array $a, array $b) => $a['inContent'] <=> $b['inContent']);

        foreach ($targets as $targetKey => $target) {
            $found = 0;
            foreach ($lowerText as $sourceKey => $text) {
                if ($sourceKey === $targetKey || isset($linked[$sourceKey][$targetKey])) {
                    continue;
                }

                foreach ($target['keywords'] as $keyword) {
                    $position = mb_strpos($text, $keyword);
                    if ($position === false) {
                        continue;
                    }

                    $start = max(0, $position - 60);
                    $opportunities[] = [
                        'source' => $sourceKey,
                        'target' => $targetKey,
                        'keyword' => $keyword,
                        'snippet' => ($start > 0 ? '…' : '') . mb_substr($linkableText[$sourceKey], $start, mb_strlen($keyword) + 120) . '…',
                    ];
                    $found++;
                    break;
                }

                if ($found >= self::MAX_OPPORTUNITIES_PER_TARGET || count($opportunities) >= self::MAX_OPPORTUNITIES) {
                    break;
                }
            }

            if (count($opportunities) >= self::MAX_OPPORTUNITIES) {
                break;
            }
        }

        return $opportunities;
    }

    private function summarize(array $opportunities): array
    {
        $summary = [
            'pages' => 0, 'indexablePages' => 0,
            'orphan' => 0, 'no_contextual' => 0, 'single_source' => 0, 'dead_end' => 0, 'deep' => 0, 'unreachable' => 0, 'too_many_links' => 0,
            'contentLinks' => 0, 'internalLinks' => 0, 'externalLinks' => 0, 'menuLinks' => 0,
            'broken' => 0, 'redirect' => 0, 'noncanonical' => 0, 'uncrawlable' => 0,
            'nofollow' => 0, 'generic' => 0, 'empty' => 0, 'long' => 0, 'adjacent' => 0, 'self' => 0, 'noindex_target' => 0,
            'opportunities' => count($opportunities),
            'avgInContent' => 0.0,
        ];

        $inContent = 0;
        foreach ($this->pages as $key => $page) {
            if ($key === 'home' || $page['type'] === 'route') {
                continue;
            }
            $summary['pages']++;
            if ($page['indexable']) {
                $summary['indexablePages']++;
                $inContent += $page['inContent'];
            }
            foreach ($page['issues'] as $issue) {
                $summary[$issue]++;
            }
        }
        $summary['avgInContent'] = $summary['indexablePages'] ? round($inContent / $summary['indexablePages'], 1) : 0.0;

        foreach ($this->links as $link) {
            if ($link['kind'] === 'menu') {
                $summary['menuLinks']++;
            } else {
                $summary['contentLinks']++;
                $summary[$link['status'] === 'external' ? 'externalLinks' : 'internalLinks']++;
            }

            if (isset($summary[$link['status']]) && in_array($link['status'], ['broken', 'redirect', 'noncanonical', 'uncrawlable'], true)) {
                $summary[$link['status']]++;
            }
            foreach ($link['flags'] as $flag) {
                $summary[$flag]++;
            }
        }

        return $summary;
    }

    /**
     * Điểm sức khỏe 0–100. Quy tắc nội bộ để theo dõi tiến độ, không phải điểm của Google.
     */
    private function score(array $s): int
    {
        $pages = max(1, $s['indexablePages']);
        $links = max(1, $s['internalLinks'] + $s['menuLinks']);
        $ratio = static fn (int $count, int $total, float $factor = 1.0) => min(1.0, $factor * $count / $total);

        $penalty = 30 * $ratio($s['orphan'] + $s['unreachable'], $pages)
            + 25 * $ratio($s['broken'] + $s['uncrawlable'], $links, 5)
            + 15 * $ratio($s['no_contextual'], $pages)
            + 10 * $ratio($s['dead_end'] + $s['deep'], $pages)
            + 10 * $ratio($s['redirect'] + $s['noncanonical'] + $s['noindex_target'], $links, 3)
            + 5 * $ratio($s['generic'] + $s['empty'] + $s['long'] + $s['adjacent'], $links, 3)
            + 5 * $ratio($s['nofollow'], $links, 5);

        return (int) max(0, round(100 - $penalty));
    }

    /**
     * @return string[]
     */
    private function resolveOwnHosts(): array
    {
        $hosts = [$this->router->getContext()->getHost()];

        // GSC_SITE_URL: "sc-domain:example.com" hoặc "https://www.example.com/".
        $site = preg_replace('#^sc-domain:#', '', $this->siteUrl);
        $site = (string) (parse_url((string) $site, PHP_URL_HOST) ?: $site);
        if ($site !== '') {
            $bare = preg_replace('#^www\.#', '', strtolower(trim($site, '/')));
            $hosts[] = $bare;
            $hosts[] = 'www.' . $bare;
        }

        return array_values(array_unique(array_filter($hosts)));
    }

    /**
     * Hai link đứng sát nhau, không có chữ nào ngăn giữa (Google: "chaining up links").
     */
    private static function isChained(\DOMElement $node): bool
    {
        for ($sibling = $node->previousSibling; $sibling !== null; $sibling = $sibling->previousSibling) {
            if ($sibling instanceof \DOMText && trim($sibling->textContent, " \t\n\r\0\x0B\xC2\xA0") === '') {
                continue;
            }

            return $sibling instanceof \DOMElement && strtolower($sibling->tagName) === 'a';
        }

        return false;
    }

    private static function collectHtml(mixed $data): string
    {
        if (is_string($data)) {
            return str_contains($data, '<a') ? ' ' . $data : '';
        }

        $html = '';
        foreach (is_array($data) ? $data : [] as $value) {
            $html .= self::collectHtml($value);
        }

        return $html;
    }

    /**
     * @return string[]
     */
    private static function splitKeywords(?string $value): array
    {
        $keywords = [];
        foreach (explode(',', (string) $value) as $keyword) {
            $keyword = mb_strtolower(self::normalizeText($keyword));
            // Từ khóa 1 từ quá chung, dễ sinh gợi ý rác.
            if ($keyword !== '' && str_contains($keyword, ' ')) {
                $keywords[$keyword] = true;
            }
        }

        return array_keys($keywords);
    }

    private static function normalizeText(string $value): string
    {
        return trim((string) preg_replace('/[\s\x{00A0}]+/u', ' ', $value));
    }
}
