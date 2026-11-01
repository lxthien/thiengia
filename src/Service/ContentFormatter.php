<?php

namespace App\Service;

use App\Utils\ConvertImages;
use App\Entity\News;
use App\Entity\NewsCategory;
use App\Enum\PostStatus;
use App\Service\SettingsManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Environment;

class ContentFormatter
{
    private $convertImages;
    private $settingsManager;
    private $requestStack;
    private $em;
    private $twig;

    /** Shortcode [bai_viet]: số bài mặc định / tối đa. */
    private const POST_LIST_DEFAULT_LIMIT = 6;
    private const POST_LIST_MAX_LIMIT = 24;

    public function __construct(ConvertImages $convertImages, SettingsManager $settingsManager, RequestStack $requestStack, EntityManagerInterface $em, Environment $twig)
    {
        $this->convertImages = $convertImages;
        $this->settingsManager = $settingsManager;
        $this->requestStack = $requestStack;
        $this->em = $em;
        $this->twig = $twig;
    }

    public function stripTagsContent($string)
    {
        // ----- remove HTML TAGs -----
        $string = preg_replace('/<[^>]*>/', ' ', $string);
        // ----- remove control characters ----- 
        $string = str_replace("\r", '', $string);
        $string = str_replace("\n", ' ', $string);
        $string = str_replace("\t", ' ', $string);

        // ----- remove multiple spaces -----
        $string = trim(preg_replace('/ {2,}/', ' ', $string));

        return $string;
    }

    public function lazyloadContent(News $post)
    {
        $content = $post->getContents();

        // Return early if no content
        if (empty($content)) {
            return '';
        }

        // Replace placeholders from settings (content_* or contents_*)
        $content = preg_replace_callback('/\[([a-zA-Z0-9_]+)\]/', function ($matches) {
            $varName = $matches[1];
            // [website] để trống trong cài đặt: dùng tên miền đang chạy, không hardcode.
            if ($varName === 'website' && (string) $this->settingsManager->get('content_website') === '') {
                $request = $this->requestStack->getCurrentRequest();

                return $request ? $request->getHost() : $matches[0];
            }
            // Try content_ prefix first
            $value = $this->settingsManager->get('content_' . $varName);
            if ($value !== null) {
                return $value;
            }
            // Try contents_ prefix as fallback
            $value = $this->settingsManager->get('contents_' . $varName);
            if ($value !== null) {
                return $value;
            }
            return $matches[0];
        }, $content);

        // Clean up spaces, dots, commas, dashes from tel: links
        $content = preg_replace_callback('/href=["\']tel:(.*?)["\']/i', function($matches) {
            $cleanedPhone = preg_replace('/[\s\.\,\-\–]+/', '', $matches[1]);
            return 'href="tel:' . $cleanedPhone . '"';
        }, $content);

        // Protect lone "<" characters that are not part of HTML tags
        // Match "<" followed by a digit, space, or other non-tag characters
        $placeholder = '___LESS_THAN_PLACEHOLDER___';
        $content = preg_replace('/<(?=[0-9\s\-\+\=\.\,])/', $placeholder, $content);

        $dom = new \DOMDocument();

        // set error level
        $internalErrors = libxml_use_internal_errors(true);

        // Wrap content to preserve structure and handle UTF-8 properly
        $wrappedContent = '<div id="lazyload-wrapper">' . $content . '</div>';
        $dom->loadHTML(
            '<?xml encoding="UTF-8">' . $wrappedContent,
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );

        // Remove the XML declaration that was added
        foreach ($dom->childNodes as $item) {
            if ($item->nodeType == XML_PI_NODE) {
                $dom->removeChild($item);
            }
        }

        // Restore error level
        libxml_use_internal_errors($internalErrors);

        $imgs = $dom->getElementsByTagName('img');

        foreach ($imgs as $img) {
            $src = $img->getAttribute('src');
            $alt = $img->getAttribute('alt');
            $width = $height = null;

            // Chỉ ảnh nội bộ dạng "/path" mới đọc kích thước / đổi sang WebP.
            // URL tuyệt đối, "//host/..." hay data: giữ nguyên src.
            if (preg_match('#^/(?!/)#', $src)) {
                $path = parse_url($src, PHP_URL_PATH) ?: $src;
                list($width, $height) = @getimagesize($this->convertImages->path($path)) ?: [null, null];

                // webpConvert2() trả về đường dẫn không có "/" đầu; khi thất bại
                // giữ src gốc (đã có "/" đầu) — không được nối thêm "/" thành "//".
                $webp = $this->convertImages->webpConvert2($path, '');
                if (is_string($webp)) {
                    $img->setAttribute('src', '/' . $webp);
                }
            }

            $img->setAttribute('loading', 'lazy');
            $img->setAttribute('alt', !empty($alt) ? $alt : $post->getTitle());
            $img->setAttribute('width', !empty($width) ? ($width > 900 ? 900 : $width) : 500);
            $img->setAttribute('height', !empty($height) ? ($width > 900 ? round(($height * 900) / $width) : $height) : 500);
        }

        // Xuất từng node con của div bọc ngoài bằng saveHTML($node): cách này
        // giữ nguyên UTF-8. saveHTML() cả document (sau khi đã bỏ khai báo
        // <?xml encoding>) đổi mọi chữ có dấu thành &acirc; / &#7885; — HTML
        // nặng gấp nhiều lần và khó đọc với bot/AI khi xem nguồn trang.
        $wrapper = (new \DOMXPath($dom))->query('//div[@id="lazyload-wrapper"]')->item(0);
        $newContent = '';
        if ($wrapper) {
            foreach ($wrapper->childNodes as $child) {
                $newContent .= $dom->saveHTML($child);
            }
        }

        // Clean up any remaining DOCTYPE, html, head, body tags
        $newContent = preg_replace('/^<!DOCTYPE[^>]*>/i', '', $newContent);
        $newContent = preg_replace('/<\/?html[^>]*>/i', '', $newContent);
        $newContent = preg_replace('/<\/?head[^>]*>/i', '', $newContent);
        $newContent = preg_replace('/<\/?body[^>]*>/i', '', $newContent);

        // Restore the "<" characters
        $newContent = str_replace($placeholder, '<', $newContent);

        // Chạy SAU bước xử lý ảnh ở trên: thẻ bài viết đã có srcset/WebP riêng,
        // không được bị gắn lại width/height/src kiểu ảnh trong nội dung.
        $newContent = $this->renderPostLists($newContent, $post);
        $newContent = $this->renderChecklistForms($newContent, $post);

        return trim($newContent);
    }

    /**
     * Shortcode danh sách bài viết:
     *
     *   [bai_viet danh_muc="12,15" so_luong="6"]
     *
     * danh_muc: ID danh mục, phân tách bằng dấu phẩy (gồm cả danh mục con của
     * chúng); bỏ trống = mọi danh mục. so_luong: số bài, mặc định 6, tối đa 24.
     * Lấy bài Post đã xuất bản, mới nhất trước, trừ chính bài đang xem.
     * Không có bài nào thì shortcode biến mất.
     */
    private function renderPostLists(string $content, News $current): string
    {
        // Shortcode::replaceBlocks lo việc CKEditor bọc shortcode trong <p>.
        return Shortcode::replaceBlocks($content, 'bai_viet', fn (string $shortcode) => $this->renderPostList($shortcode, $current));
    }

    /**
     * Shortcode form nhận checklist — xem Shortcode::CHECKLIST. Link file KHÔNG
     * được in ra HTML: sau khi khách gửi form, ContactController::checklistLeadAction
     * đọc lại đúng shortcode thứ $index trong nội dung gốc rồi mới trả link về.
     */
    private function renderChecklistForms(string $content, News $post): string
    {
        $index = 0;

        return Shortcode::replaceBlocks($content, Shortcode::CHECKLIST, function (string $shortcode) use ($post, &$index) {
            $attributes = Shortcode::attributes($shortcode);

            return $this->twig->render('v3/components/_checklist_form.html.twig', [
                'post' => $post,
                'index' => $index++,
                'title' => $attributes['tieu_de'] ?? '',
                'intro' => $attributes['mo_ta'] ?? '',
                'hasFiles' => Shortcode::checklistFiles($attributes) !== [],
            ]);
        });
    }

    private function renderPostList(string $shortcode, News $current): string
    {
        $params = Shortcode::attributes($shortcode);

        $limit = (int) ($params['so_luong'] ?? self::POST_LIST_DEFAULT_LIMIT);
        $limit = max(1, min(self::POST_LIST_MAX_LIMIT, $limit ?: self::POST_LIST_DEFAULT_LIMIT));

        $categoryIds = array_values(array_filter(array_map('intval', preg_split('/[\s,]+/', $params['danh_muc'] ?? ''))));

        $qb = $this->em->createQueryBuilder()
            ->select('DISTINCT n')->from(News::class, 'n')
            ->where('n.status = :status')
            ->andWhere('n.postType = :postType')
            ->setParameter('status', PostStatus::Published)
            ->setParameter('postType', 'post')
            ->orderBy('n.createdAt', 'DESC')
            ->setMaxResults($limit);

        if ($categoryIds !== []) {
            $qb->innerJoin('n.category', 'c')
                ->andWhere('c.id IN (:categories)')
                ->setParameter('categories', $this->withChildCategories($categoryIds));
        }

        if ($current->getId()) {
            $qb->andWhere('n.id != :current')->setParameter('current', $current->getId());
        }

        $posts = $qb->getQuery()->getResult();

        return $posts ? $this->twig->render('v3/components/_post_list_shortcode.html.twig', ['posts' => $posts]) : '';
    }

    /**
     * @param list<int> $ids
     *
     * @return list<int>
     */
    private function withChildCategories(array $ids): array
    {
        $children = $this->em->createQueryBuilder()
            ->select('c.id')->from(NewsCategory::class, 'c')
            ->where('IDENTITY(c.parentcat) IN (:ids)')
            ->andWhere('c.enable = true')
            ->setParameter('ids', $ids)
            ->getQuery()->getSingleColumnResult();

        return array_values(array_unique(array_merge($ids, array_map('intval', $children))));
    }
}
