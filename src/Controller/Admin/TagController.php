<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Controller\Admin;

use App\Entity\ActivityLog;
use App\Entity\Tag;
use App\Form\TagType;
use App\Service\ActivityLogService;
use App\Utils\Slugger;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Controller used to manage tag contents in the backend.
 */
#[Route('/admin/tag')]
#[IsGranted('ROLE_EDITOR')]
class TagController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ActivityLogService $activityLogService,
    ) {
    }

    private const PER_PAGE = 30;
    private const SORTS = ['name' => 'Tên A → Z', 'posts' => 'Nhiều bài viết nhất', 'newest' => 'Mới tạo gần đây'];

    /**
     * Danh sách thẻ: tìm theo tên/URL, lọc thẻ đang dùng / chưa gắn bài, sắp xếp, phân trang.
     */
    #[Route('/', name: 'admin_tag_index', methods: ['GET'])]
    public function indexAction(Request $request, PaginatorInterface $paginator)
    {
        $filters = $this->filters($request);

        $qb = $this->em->createQueryBuilder()
            ->select('t')->addSelect('SIZE(t.news) AS HIDDEN newsCount')
            ->from(Tag::class, 't');

        if ($filters['q'] !== '') {
            $qb->andWhere('t.name LIKE :q OR t.url LIKE :q OR t.pageTitle LIKE :q')
                ->setParameter('q', '%'.addcslashes($filters['q'], '%_').'%');
        }
        if ($filters['usage'] === 'used') {
            $qb->andWhere('SIZE(t.news) > 0');
        } elseif ($filters['usage'] === 'unused') {
            $qb->andWhere('SIZE(t.news) = 0');
        }

        match ($filters['sort']) {
            'posts' => $qb->orderBy('newsCount', 'DESC')->addOrderBy('t.name', 'ASC'),
            'newest' => $qb->orderBy('t.id', 'DESC'),
            default => $qb->orderBy('t.name', 'ASC'),
        };

        $pagination = $paginator->paginate($qb, max(1, $request->query->getInt('page', 1)), self::PER_PAGE, ['distinct' => false]);
        $ids = array_map(fn (Tag $tag) => $tag->getId(), iterator_to_array($pagination));

        return $this->render('admin/tag/index.html.twig', [
            'pagination' => $pagination,
            'news_counts' => $this->newsCounts($ids),
            'total' => (int) $this->em->createQuery('SELECT COUNT(t.id) FROM '.Tag::class.' t')->getSingleScalarResult(),
            'unused' => (int) $this->em->createQuery('SELECT COUNT(t.id) FROM '.Tag::class.' t WHERE SIZE(t.news) = 0')->getSingleScalarResult(),
            'filters' => $filters,
            'sorts' => self::SORTS,
        ]);
    }

    /**
     * @return array{q: string, usage: string, sort: string}
     */
    private function filters(Request $request): array
    {
        $usage = (string) $request->query->get('usage', '');
        $sort = (string) $request->query->get('order', 'name');

        return [
            'q' => trim((string) $request->query->get('q', '')),
            'usage' => in_array($usage, ['used', 'unused'], true) ? $usage : '',
            'sort' => isset(self::SORTS[$sort]) ? $sort : 'name',
        ];
    }

    /**
     * Số bài viết gắn từng thẻ (tính cả bài nháp — thẻ vẫn đang được dùng).
     *
     * @param list<int> $ids
     *
     * @return array<int, int>
     */
    private function newsCounts(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $rows = $this->em->createQueryBuilder()
            ->select('t.id AS id', 'COUNT(n.id) AS total')
            ->from(Tag::class, 't')->leftJoin('t.news', 'n')
            ->where('t.id IN (:ids)')->setParameter('ids', $ids)
            ->groupBy('t.id')
            ->getQuery()->getArrayResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['id']] = (int) $row['total'];
        }

        return $counts;
    }

    /**
     * Displays a form to edit an existing Tag entity.
     */
    #[Route('/{id}/edit', requirements: ['id' => '\d+'], name: 'admin_tag_edit', methods: ['GET', 'POST'])]
    public function editAction(Request $request, Tag $tag, Slugger $slugger)
    {
        //$this->denyAccessUnlessGranted('edit', $category, 'Posts can only be edited by their authors.');

        $form = $this->createForm(TagType::class, $tag);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Capture changes before flush
            $diffDetails = $this->activityLogService->getEntityDiff($tag);

            $this->em->flush();

            // Activity Log
            $this->activityLogService->log(
                ActivityLog::ACTION_UPDATE,
                ActivityLog::ENTITY_TAG,
                $tag->getId(),
                $tag->getName(),
                $diffDetails
            );

            $this->addFlash('success', 'action.updated_successfully');
            return $this->redirectToRoute('admin_tag_index');
        }

        return $this->render('admin/tag/edit.html.twig', [
            'object' => $tag,
            'form' => $form->createView(),
            'news_count' => $this->newsCounts([$tag->getId()])[$tag->getId()] ?? 0,
        ]);
    }

    /**
     * Deletes a Tag entity.
     */
    #[Route('/{id}/delete', name: 'admin_tag_delete', methods: ['POST'])]
    public function deleteAction(Request $request, Tag $tag)
    {
        if (!$this->isCsrfTokenValid('delete', $request->request->get('token'))) {
            return $this->redirectToRoute('admin_tag_index');
        }

        $tagName = $tag->getName();
        $tagId = $tag->getId();

        $this->em->remove($tag);
        $this->em->flush();

        // Activity Log
        $this->activityLogService->log(
            ActivityLog::ACTION_DELETE,
            ActivityLog::ENTITY_TAG,
            $tagId,
            $tagName
        );

        $this->addFlash('success', 'action.deleted_successfully');

        return $this->redirectToRoute('admin_tag_index');
    }
}
