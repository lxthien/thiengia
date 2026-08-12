<?php

namespace App\Controller\Admin;

use App\Entity\ContentDecaySnapshot;
use App\Entity\NewsCategory;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/content-decay')]
#[IsGranted('ROLE_EDITOR')]
class ContentDecayController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PaginatorInterface $paginator,
    ) {
    }

    /**
     * Đọc từ content_decay_snapshot (tính sẵn bởi app:compute-content-decay
     * qua cron) — không phân tích lại (word count, v.v.) lúc mở trang.
     */
    #[Route('/', name: 'admin_content_decay_index', methods: ['GET'])]
    public function indexAction(Request $request)
    {
        $filters = [
            'q' => trim((string) $request->query->get('q')),
            'category' => $request->query->get('category', ''),
            'age' => $request->query->getInt('age', 180),
            'seo' => $request->query->getInt('seo', 80),
            'indexable' => $request->query->get('indexable', '1'),
            'sort' => $request->query->get('sort', 'decay_desc'),
        ];

        $qb = $this->em->getRepository(ContentDecaySnapshot::class)->createQueryBuilder('s')
            ->innerJoin('s.news', 'n')
            ->addSelect('n')
            // Join riêng CHỈ để hiển thị (post.category trong template cần đủ
            // toàn bộ danh mục của bài, không phải chỉ danh mục khớp filter) —
            // addSelect ở đây không bị giới hạn bởi điều kiện filter category
            // bên dưới (dùng alias catFilter riêng), tránh corrupt collection
            // giống bài học ở NewsRepository (xem comment trong file đó).
            ->leftJoin('n.category', 'catDisplay')
            ->addSelect('catDisplay');

        if ($filters['q'] !== '') {
            $qb->andWhere('n.title LIKE :q OR n.url LIKE :q OR n.description LIKE :q')
                ->setParameter('q', '%' . $filters['q'] . '%');
        }

        if ($filters['category'] !== '') {
            $qb->leftJoin('n.category', 'catFilter')
                ->andWhere('catFilter.id = :categoryId')
                ->setParameter('categoryId', $filters['category']);
        }

        if ($filters['age'] > 0) {
            $qb->andWhere('s.ageDays >= :age')->setParameter('age', $filters['age']);
        }

        if ($filters['seo'] > 0) {
            $qb->andWhere('s.seoScore <= :seo')->setParameter('seo', $filters['seo']);
        }

        if ($filters['indexable'] === '1') {
            $qb->andWhere('s.isIndexable = :indexable')->setParameter('indexable', true);
        }

        switch ($filters['sort']) {
            case 'updated_asc':
                $qb->orderBy('n.updatedAt', 'ASC');
                break;
            case 'views_asc':
                $qb->orderBy('s.views', 'ASC');
                break;
            case 'seo_asc':
                $qb->orderBy('s.seoScore', 'ASC');
                break;
            case 'age_desc':
                $qb->orderBy('s.ageDays', 'DESC');
                break;
            case 'decay_desc':
            default:
                $qb->orderBy('s.decayScore', 'DESC')->addOrderBy('s.ageDays', 'DESC');
                break;
        }

        $pagination = $this->paginator->paginate(
            $qb,
            $request->query->getInt('page', 1),
            30
        );

        $items = [];
        foreach ($pagination as $snapshot) {
            $items[] = $this->toItem($snapshot);
        }
        $pagination->setItems($items);

        $categories = $this->em->getRepository(NewsCategory::class)->findBy([], ['name' => 'ASC']);

        return $this->render('admin/content_decay/index.html.twig', [
            'pagination' => $pagination,
            'summary' => $this->summarize($qb),
            'categories' => $categories,
            'filters' => $filters,
        ]);
    }

    /**
     * Giữ nguyên hình dạng mảng cũ (post/decayScore/seo.score/...) để không
     * phải sửa template.
     */
    private function toItem(ContentDecaySnapshot $snapshot): array
    {
        return [
            'post' => $snapshot->getNews(),
            'decayScore' => $snapshot->getDecayScore(),
            'decayStatus' => $snapshot->getDecayStatus(),
            'ageDays' => $snapshot->getAgeDays(),
            'views' => $snapshot->getViews(),
            'seo' => [
                'score' => $snapshot->getSeoScore(),
                'status' => $snapshot->getSeoStatus(),
            ],
            'reasons' => $snapshot->getReasons(),
            'recommendations' => $snapshot->getRecommendations(),
            'isIndexable' => $snapshot->isIndexable(),
        ];
    }

    /**
     * Tổng hợp summary trên TOÀN BỘ kết quả đã lọc (không chỉ trang hiện
     * tại) — chạy 1 query aggregate riêng, rẻ hơn nhiều so với việc load
     * hết bản ghi rồi cộng dồn trong PHP như bản cũ.
     */
    private function summarize($qb): array
    {
        $countQb = (clone $qb)
            ->resetDQLPart('orderBy')
            ->select('COUNT(s.news) as totalCount', 'AVG(s.decayScore) as avgScore')
            ->setMaxResults(null)
            ->setFirstResult(null);

        $result = $countQb->getQuery()->getOneOrNullResult();
        $total = (int) ($result['totalCount'] ?? 0);
        $avg = $result['avgScore'] ?? 0;

        $highRiskQb = (clone $qb)
            ->resetDQLPart('orderBy')
            ->select('COUNT(s.news)')
            ->andWhere('s.decayScore >= 70')
            ->setMaxResults(null)
            ->setFirstResult(null);

        $needsRefreshQb = (clone $qb)
            ->resetDQLPart('orderBy')
            ->select('COUNT(s.news)')
            ->andWhere('s.decayScore >= 40 AND s.decayScore < 70')
            ->setMaxResults(null)
            ->setFirstResult(null);

        return [
            'total' => $total,
            'highRisk' => (int) $highRiskQb->getQuery()->getSingleScalarResult(),
            'needsRefresh' => (int) $needsRefreshQb->getQuery()->getSingleScalarResult(),
            'averageDecayScore' => $total ? (int) round($avg) : 0,
        ];
    }
}
