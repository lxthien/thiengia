<?php

namespace App\Controller\Admin;

use App\Entity\News;
use App\Entity\NewsCategory;
use App\Seo\ContentDecayReporter;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/content-decay')]
#[IsGranted('ROLE_ADMIN')]
class ContentDecayController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PaginatorInterface $paginator,
    ) {
    }

    #[Route('/', name: 'admin_content_decay_index', methods: ['GET'])]
    public function indexAction(Request $request, ContentDecayReporter $reporter)
    {
        $filters = [
            'q' => trim((string) $request->query->get('q')),
            'category' => $request->query->get('category', ''),
            'age' => $request->query->getInt('age', 180),
            'seo' => $request->query->getInt('seo', 80),
            'indexable' => $request->query->get('indexable', '1'),
            'sort' => $request->query->get('sort', 'decay_desc'),
        ];

        $qb = $this->em->getRepository(News::class)->createQueryBuilder('n')
            ->leftJoin('n.category', 'c')
            ->addSelect('c')
            ->where('n.postType = :postType')
            ->andWhere('n.enable = :enable')
            ->setParameter('postType', 'post')
            ->setParameter('enable', true)
            ->orderBy('n.updatedAt', 'ASC');

        if ($filters['q'] !== '') {
            $qb->andWhere('n.title LIKE :q OR n.url LIKE :q OR n.description LIKE :q')
                ->setParameter('q', '%' . $filters['q'] . '%');
        }

        if ($filters['category'] !== '') {
            $qb->andWhere('c.id = :categoryId')
                ->setParameter('categoryId', $filters['category']);
        }

        $posts = $qb->getQuery()->getResult();
        $items = [];

        foreach ($posts as $post) {
            $item = $reporter->analyze($post);

            if ($filters['age'] > 0 && $item['ageDays'] < $filters['age']) {
                continue;
            }

            if ($filters['seo'] > 0 && $item['seo']['score'] > $filters['seo']) {
                continue;
            }

            // kientruc indexable filter logic uses the analyzed flag from robots
            if ($filters['indexable'] === '1' && !$item['isIndexable']) {
                continue;
            }

            $items[] = $item;
        }

        $this->sortItems($items, $filters['sort']);

        $pagination = $this->paginator->paginate(
            $items,
            $request->query->getInt('page', 1),
            30
        );

        $categories = $this->em->getRepository(NewsCategory::class)->findBy([], ['name' => 'ASC']);

        return $this->render('admin/content_decay/index.html.twig', [
            'pagination' => $pagination,
            'summary' => $reporter->summarize($items),
            'categories' => $categories,
            'filters' => $filters,
        ]);
    }

    private function sortItems(array &$items, $sort)
    {
        usort($items, function ($a, $b) use ($sort) {
            switch ($sort) {
                case 'updated_asc':
                    return $a['post']->getUpdatedAt()->getTimestamp() <=> $b['post']->getUpdatedAt()->getTimestamp();
                case 'views_asc':
                    return $a['views'] <=> $b['views'];
                case 'seo_asc':
                    return $a['seo']['score'] <=> $b['seo']['score'];
                case 'age_desc':
                    return $b['ageDays'] <=> $a['ageDays'];
                case 'decay_desc':
                default:
                    if ($a['decayScore'] === $b['decayScore']) {
                        return $b['ageDays'] <=> $a['ageDays'];
                    }

                    return $b['decayScore'] <=> $a['decayScore'];
            }
        });
    }
}
