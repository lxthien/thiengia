<?php

namespace App\Controller\Admin;

use App\Repository\InternalLinkReportRepository;
use App\Seo\InternalLink\InternalLinkAnalyzer;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/internal-links')]
#[IsGranted('ROLE_EDITOR')]
class InternalLinkController extends AbstractController
{
    private const PER_PAGE = 50;

    // level: critical = Google nêu rõ / gây lỗi thật; warning = nên sửa; info = rà soát.
    private const PAGE_ISSUES = [
        'orphan' => ['label' => 'Trang mồ côi', 'level' => 'critical', 'help' => 'Không có link nào trỏ tới (nội dung, menu, danh mục). Google yêu cầu mọi trang quan trọng có ít nhất 1 link từ trang khác.'],
        'unreachable' => ['label' => 'Không tới được từ trang chủ', 'level' => 'critical', 'help' => 'Có link trỏ tới nhưng không có đường đi nào bắt đầu từ trang chủ.'],
        'no_contextual' => ['label' => 'Chưa có link trong nội dung', 'level' => 'warning', 'help' => 'Chỉ được link từ menu hoặc trang danh mục. Thêm link từ bài liên quan để Google hiểu ngữ cảnh của trang.'],
        'dead_end' => ['label' => 'Trang cụt', 'level' => 'warning', 'help' => 'Nội dung không có link nội bộ nào đi ra. Nên dẫn người đọc tới nội dung liên quan.'],
        'deep' => ['label' => 'Nằm quá sâu', 'level' => 'warning', 'help' => 'Cần hơn ' . InternalLinkAnalyzer::MAX_CLICK_DEPTH . ' lần click từ trang chủ mới tới.'],
        'single_source' => ['label' => 'Chỉ 1 trang link tới', 'level' => 'info', 'help' => 'Chỉ có đúng 1 trang link tới trong nội dung và không nằm trong menu.'],
        'too_many_links' => ['label' => 'Quá nhiều link', 'level' => 'info', 'help' => 'Mật độ link trong nội dung cao bất thường. Google: nếu thấy nhiều quá thì có lẽ là nhiều quá.'],
    ];

    private const LINK_ISSUES = [
        'broken' => ['label' => 'Link hỏng (404)', 'level' => 'critical', 'help' => 'Đích không tồn tại hoặc chưa xuất bản.'],
        'uncrawlable' => ['label' => 'Link Google không đi theo được', 'level' => 'critical', 'help' => 'Thẻ <a> không có href thật hoặc dùng javascript:.'],
        'redirect' => ['label' => 'Link qua chuyển hướng', 'level' => 'warning', 'help' => 'Nên sửa link trỏ thẳng tới URL đích.'],
        'noncanonical' => ['label' => 'Link tới URL không chuẩn', 'level' => 'warning', 'help' => 'Trang mở được nhưng không phải URL canonical.'],
        'nofollow' => ['label' => 'Link nội bộ gắn nofollow', 'level' => 'warning', 'help' => 'nofollow chỉ nên dùng cho link ngoài không tin cậy.'],
        'noindex_target' => ['label' => 'Link tới trang noindex', 'level' => 'warning', 'help' => 'Trang đích đang chặn index.'],
        'empty' => ['label' => 'Anchor rỗng', 'level' => 'warning', 'help' => 'Link không có chữ, hoặc là ảnh thiếu alt.'],
        'generic' => ['label' => 'Anchor chung chung', 'level' => 'warning', 'help' => 'Kiểu "xem thêm", "tại đây": không cho biết trang đích nói về gì.'],
        'long' => ['label' => 'Anchor quá dài', 'level' => 'info', 'help' => 'Hơn ' . InternalLinkAnalyzer::LONG_ANCHOR_WORDS . ' từ. Anchor nên ngắn gọn.'],
        'adjacent' => ['label' => 'Link đứng sát nhau', 'level' => 'info', 'help' => 'Hai link liền nhau không có chữ ngăn giữa, khó phân biệt.'],
        'self' => ['label' => 'Link tới chính nó', 'level' => 'info', 'help' => 'Trang tự link về chính mình.'],
    ];

    public function __construct(
        private readonly InternalLinkReportRepository $reports,
        private readonly PaginatorInterface $paginator,
    ) {
    }

    #[Route('/', name: 'admin_internal_link_index', methods: ['GET'])]
    public function indexAction(Request $request)
    {
        $latest = $this->reports->findLatest();
        if (!$latest) {
            return $this->render('admin/internal_link/index.html.twig', ['report' => null] + $this->labels());
        }

        $data = $latest->getData();
        $tab = $request->query->get('tab', 'overview');
        $filters = [
            'q' => mb_strtolower(trim((string) $request->query->get('q'))),
            'issue' => (string) $request->query->get('issue', ''),
            'type' => (string) $request->query->get('type', ''),
        ];

        $items = match ($tab) {
            'pages' => $this->filterPages($data, $filters),
            'links' => $this->filterLinks($data, $filters),
            'opportunities' => $this->filterOpportunities($data, $filters),
            default => [],
        };

        return $this->render('admin/internal_link/index.html.twig', [
            'report' => $data,
            'generatedAt' => $latest->getGeneratedAt(),
            'tab' => in_array($tab, ['pages', 'links', 'opportunities'], true) ? $tab : 'overview',
            'filters' => $filters,
            'pagination' => $this->paginator->paginate($items, max(1, $request->query->getInt('page', 1)), self::PER_PAGE),
        ] + $this->labels());
    }

    #[Route('/page', name: 'admin_internal_link_page', methods: ['GET'])]
    public function pageAction(Request $request)
    {
        $latest = $this->reports->findLatest();
        $data = $latest ? $latest->getData() : [];
        $key = (string) $request->query->get('key');

        if (!isset($data['pages'][$key])) {
            throw $this->createNotFoundException('Trang không có trong báo cáo link nội bộ.');
        }

        return $this->render('admin/internal_link/page.html.twig', [
            'report' => $data,
            'generatedAt' => $latest->getGeneratedAt(),
            'page' => $data['pages'][$key],
            'inbound' => array_values(array_filter($data['links'], static fn (array $link) => $link['target'] === $key && $link['source'] !== $key)),
            'outbound' => array_values(array_filter($data['links'], static fn (array $link) => $link['source'] === $key)),
            'opportunities' => array_values(array_filter($data['opportunities'], static fn (array $item) => $item['target'] === $key)),
        ] + $this->labels());
    }

    #[Route('/refresh', name: 'admin_internal_link_refresh', methods: ['POST'])]
    public function refreshAction(Request $request, InternalLinkAnalyzer $analyzer)
    {
        if (!$this->isCsrfTokenValid('internal_link_refresh', $request->request->get('token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $this->reports->refresh($analyzer);
        $this->addFlash('success', 'Đã phân tích lại link nội bộ.');

        return $this->redirectToRoute('admin_internal_link_index');
    }

    private function filterPages(array $data, array $filters): array
    {
        $pages = array_filter($data['pages'], static function (array $page) use ($filters) {
            if ($page['key'] === 'home') {
                return false;
            }
            if ($filters['issue'] === 'any' ? !$page['issues'] : ($filters['issue'] !== '' && !in_array($filters['issue'], $page['issues'], true))) {
                return false;
            }
            if ($filters['type'] !== '' && $page['type'] !== $filters['type']) {
                return false;
            }

            return $filters['q'] === '' || str_contains(mb_strtolower($page['title'] . ' ' . $page['path']), $filters['q']);
        });

        // Trang có vấn đề nặng nhất lên đầu, sau đó tới trang ít link ngữ cảnh nhất.
        $weight = static fn (array $page) => array_sum(array_map(
            static fn (string $issue) => ['critical' => 100, 'warning' => 10, 'info' => 1][self::PAGE_ISSUES[$issue]['level'] ?? 'info'],
            $page['issues']
        ));
        usort($pages, static fn (array $a, array $b) => [$weight($b), $a['inContent']] <=> [$weight($a), $b['inContent']]);

        return $pages;
    }

    private function filterLinks(array $data, array $filters): array
    {
        $links = [];
        foreach ($data['links'] as $link) {
            $issues = $link['flags'];
            if (isset(self::LINK_ISSUES[$link['status']])) {
                array_unshift($issues, $link['status']);
            }

            if ($filters['issue'] === 'all') {
                if ($link['status'] === 'external') {
                    continue;
                }
            } elseif ($filters['issue'] === '' ? !$issues : !in_array($filters['issue'], $issues, true)) {
                continue;
            }

            $source = $data['pages'][$link['source']] ?? null;
            if ($filters['q'] !== '' && !str_contains(mb_strtolower($link['href'] . ' ' . $link['anchor'] . ' ' . ($source['title'] ?? 'menu')), $filters['q'])) {
                continue;
            }

            $links[] = $link + ['issues' => $issues];
        }

        return $links;
    }

    private function filterOpportunities(array $data, array $filters): array
    {
        if ($filters['q'] === '') {
            return $data['opportunities'];
        }

        return array_values(array_filter($data['opportunities'], static fn (array $item) => str_contains(
            mb_strtolower($item['keyword'] . ' ' . $data['pages'][$item['source']]['title'] . ' ' . $data['pages'][$item['target']]['title']),
            $filters['q']
        )));
    }

    private function labels(): array
    {
        return [
            'pageIssues' => self::PAGE_ISSUES,
            'linkIssues' => self::LINK_ISSUES,
            'typeLabels' => ['post' => 'Bài viết', 'page' => 'Trang', 'category' => 'Danh mục', 'route' => 'Trang chức năng', 'home' => 'Trang chủ'],
        ];
    }
}
