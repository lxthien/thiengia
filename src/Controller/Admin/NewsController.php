<?php

namespace App\Controller\Admin;

use App\Entity\ActivityLog;
use App\Entity\NewsCategory;
use App\Entity\News;
use App\Entity\Rating;
use App\Enum\PostStatus;
use App\Form\NewsCategoryType;
use App\Form\NewsType;
use App\Service\ActivityLogService;
use App\Utils\Slugger;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Security\Voter\NewsVoter;

/**
 * Controller used to manage post contents in the backend.
 */
#[Route('/admin/news')]
#[IsGranted('ROLE_AUTHOR')]
class NewsController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ActivityLogService $activityLogService,
        private readonly PaginatorInterface $paginator,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Lists all News entities.
     */
    #[Route('/', name: 'admin_news_index', methods: ['GET'])]
    public function indexAction(Request $request)
    {
        $repository = $this->em->getRepository(News::class);
        $filters = $this->readFilters($request);

        // ROLE_AUTHOR chỉ thấy bài viết của chính mình; ROLE_EDITOR trở lên thấy tất cả.
        $authorFilter = $this->isGranted('ROLE_EDITOR') ? null : $this->getUser();

        $pagination = $this->paginator->paginate(
            $repository->filterPosts([
                'q' => $filters['q'],
                'status' => PostStatus::tryFrom($filters['status']),
                'category' => $filters['category'],
            ], $authorFilter),
            max(1, $request->query->getInt('page', 1)),
            20
        );

        $statusCounts = $repository->countPostsByStatus($authorFilter);

        return $this->render('admin/news/index.html.twig', [
            'pagination' => $pagination,
            'filters' => $filters,
            'statuses' => PostStatus::cases(),
            'status_counts' => $statusCounts,
            'total_posts' => array_sum($statusCounts),
            'categories' => $this->em->getRepository(NewsCategory::class)->findBy([], ['name' => 'ASC']),
        ]);
    }

    /**
     * Link cũ theo danh mục — nay là bộ lọc "Danh mục" của danh sách bài viết.
     */
    #[Route('/list/{categoryId}', name: 'admin_news_list_by_category', requirements: ['categoryId' => '\d+'], methods: ['GET'])]
    public function listAction(Request $request, int $categoryId)
    {
        return $this->redirectToRoute('admin_news_index', array_filter([
            'category' => $categoryId,
            'q' => trim((string) $request->query->get('q', '')),
        ]));
    }

    /**
     * @return array{q: string, status: string, category: ?int}
     */
    private function readFilters(Request $request): array
    {
        $status = (string) $request->query->get('status', '');
        $category = $request->query->getInt('category');

        return [
            'q' => trim((string) $request->query->get('q', '')),
            'status' => null !== PostStatus::tryFrom($status) ? $status : '',
            'category' => $category > 0 ? $category : null,
        ];
    }

    /**
     * Quay lại danh sách, giữ nguyên bộ lọc và trang đang xem (truyền qua query của action).
     */
    private function redirectToIndex(Request $request)
    {
        $filters = $this->readFilters($request);
        $page = $request->query->getInt('page', 1);

        return $this->redirectToRoute('admin_news_index', array_filter($filters + ['page' => $page > 1 ? $page : null]));
    }

    /**
     * Searches published posts and pages for the CKEditor content block tool.
     */
    #[Route('/content-block/related-search', name: 'admin_content_block_related_search', methods: ['GET'])]
    public function relatedSearchAction(Request $request)
    {
        $query = trim((string) $request->query->get('q', ''));
        $currentId = $request->query->getInt('currentId', 0);

        if (mb_strlen($query) < 2) {
            return new JsonResponse(['items' => []]);
        }

        $items = $this->em->getRepository(News::class)
            ->createQueryBuilder('n')
            ->where('n.status = :status')
            ->andWhere('n.id != :currentId')
            ->andWhere('n.title LIKE :query OR n.url LIKE :query OR n.description LIKE :query')
            ->setParameter('status', PostStatus::Published)
            ->setParameter('currentId', $currentId)
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('n.createdAt', 'DESC')
            ->setMaxResults(12)
            ->getQuery()
            ->getResult();

        return new JsonResponse([
            'items' => array_map(function (News $news) {
                return [
                    'id' => $news->getId(),
                    'title' => $news->getTitle(),
                    'url' => $this->generateUrl('dynamic_post_page', ['slug' => $news->getUrl()]),
                    'description' => $news->getDescription(),
                ];
            }, $items),
        ]);
    }

    /**
     * Creates a new News entity.
     */
    #[Route('/new', name: 'admin_news_new', methods: ['GET', 'POST'])]
    public function newAction(Request $request, Slugger $slugger)
    {
        $news = new News();
        $news->setAuthor($this->getUser());

        $form = $this->createForm(NewsType::class, $news)
            ->add('save', SubmitType::class)
            ->add('saveAndCreateNew', SubmitType::class);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->em->persist($news);
                $this->em->flush();

                // Update Ordering for post
                $news->setOrdering( $news->getId() );
                $this->em->flush();

                // Activity Log
                $this->activityLogService->log(
                    ActivityLog::ACTION_CREATE,
                    ActivityLog::ENTITY_NEWS,
                    $news->getId(),
                    $news->getTitle()
                );

                $this->addFlash('success', 'action.created_successfully');

                if ($form->get('saveAndCreateNew')->isClicked()) {
                    return $this->redirectToRoute('admin_news_new');
                }

                return $this->redirectToRoute('admin_news_edit', array(
                    'id' => $news->getId()
                ));
            } catch (\Throwable $e) {
                $this->logger->error('Unable to create news.', ['exception' => $e]);
                $message = 'Không thể lưu bài viết lúc này. Vui lòng thử lại hoặc liên hệ quản trị viên.';
            }

            $this->addFlash('error', $message);

            return $this->render('admin/news/new.html.twig', [
                'news' => $news,
                'form' => $form->createView(),
            ]);
        }

        return $this->render('admin/news/new.html.twig', [
            'news' => $news,
            'form' => $form->createView(),
        ]);
    }

    /**
     * Displays a form to edit an existing News entity.
     */
    #[Route('/{id}/edit', requirements: ['id' => '\d+'], name: 'admin_news_edit', methods: ['GET', 'POST'])]
    public function editAction(Request $request, News $news, Slugger $slugger)
    {
        $this->denyAccessUnlessGranted(NewsVoter::EDIT, $news);

        $form = $this->createForm(NewsType::class, $news)
            ->add('save', SubmitType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $unitOfWork = $this->em->getUnitOfWork();
                $originalData = $unitOfWork->getOriginalEntityData($news);

                // Handle postType change logic
                $originalPostType = isset($originalData['postType']) ? $originalData['postType'] : 'post';
                $newPostType = $news->getPostType();

                // From post to page
                if ($originalPostType === 'post' && $newPostType === 'page') {
                    // Remove relationships with categories and tags
                    $news->getCategory()->clear();
                    $news->getTags()->clear();
                    // Ensure parent is null for new pages
                    $news->setParent(null);
                }
                // From page to post
                elseif ($originalPostType === 'page' && $newPostType === 'post') {
                    // Remove parent relationship
                    $news->setParent(null);
                }

                // Capture changes before flush
                $diffDetails = $this->activityLogService->getEntityDiff($news);

                $this->em->flush();

                // Activity Log
                $this->activityLogService->log(
                    ActivityLog::ACTION_UPDATE,
                    ActivityLog::ENTITY_NEWS,
                    $news->getId(),
                    $news->getTitle(),
                    $diffDetails
                );

                $this->addFlash('success', 'action.updated_successfully');

                // If postType changed to page, redirect to page edit
                if ($originalPostType === 'post' && $newPostType === 'page') {
                    return $this->redirectToRoute('admin_page_edit', array(
                        'id' => $news->getId()
                    ));
                }

                return $this->redirectToRoute('admin_news_edit', array(
                    'id' => $news->getId()
                ));
            } catch (\Throwable $e) {
                $this->logger->error('Unable to update news.', ['exception' => $e, 'newsId' => $news->getId()]);
                $message = 'Không thể cập nhật bài viết lúc này. Vui lòng thử lại hoặc liên hệ quản trị viên.';
            }

            $this->addFlash('error', $message);

            return $this->render('admin/news/edit.html.twig', [
                'news' => $news,
                'form' => $form->createView(),
            ]);
        }

        return $this->render('admin/news/edit.html.twig', [
            'news' => $news,
            'form' => $form->createView(),
        ]);
    }

    /**
     * Deletes a News entity.
     */
    #[Route('/{id}/delete', methods: ['POST'], name: 'admin_news_delete')]
    public function deleteAction(Request $request, $id, News $news)
    {
        $this->denyAccessUnlessGranted(NewsVoter::DELETE, $news);

        if (!$this->isCsrfTokenValid('delete', $request->request->get('token'))) {
            return $this->redirectToIndex($request);
        }

        $newsTitle = $news->getTitle();
        $newsId = $news->getId();

        $news->getTags()->clear();

        $this->em->remove($news);
        $this->em->flush();

        // Activity Log
        $this->activityLogService->log(
            ActivityLog::ACTION_DELETE,
            ActivityLog::ENTITY_NEWS,
            $newsId,
            $newsTitle
        );

        $this->addFlash('success', 'action.deleted_successfully');

        return $this->redirectToIndex($request);
    }

    /**
     * Đổi trạng thái hàng loạt từ danh sách admin (chọn nhiều bài viết + 1 thao tác).
     * Thay cho công tắc bật/tắt nhanh cũ trên từng dòng — 5 trạng thái không hợp
     * để diễn đạt bằng 1 công tắc on/off nữa, đổi trạng thái giờ qua đây hoặc
     * qua dropdown "Trạng thái" trong form sửa từng bài.
     */
    #[Route('/bulk', name: 'admin_news_bulk', methods: ['POST'])]
    public function bulkAction(Request $request)
    {
        if (!$this->isCsrfTokenValid('bulk_news', $request->request->get('token'))) {
            $this->addFlash('error', 'Phiên làm việc đã hết hạn, vui lòng thử lại.');
            return $this->redirectToIndex($request);
        }

        $ids = $request->request->all('ids');
        $bulkAction = (string) $request->request->get('bulk_action');

        $statusMap = [
            'publish' => PostStatus::Published,
            'draft' => PostStatus::Draft,
            'pending_review' => PostStatus::PendingReview,
            'archive' => PostStatus::Archived,
        ];

        if (empty($ids) || !isset($statusMap[$bulkAction])) {
            $this->addFlash('error', 'Vui lòng chọn bài viết và thao tác hợp lệ.');
            return $this->redirectToIndex($request);
        }

        $newStatus = $statusMap[$bulkAction];
        $newsList = $this->em->getRepository(News::class)->findBy(['id' => $ids]);

        $count = 0;
        foreach ($newsList as $news) {
            if (!$this->isGranted(NewsVoter::EDIT, $news)) {
                continue;
            }

            $news->setStatus($newStatus);
            $count++;
        }

        $this->em->flush();

        $this->activityLogService->log(
            ActivityLog::ACTION_TOGGLE,
            ActivityLog::ENTITY_NEWS,
            null,
            sprintf('%d bài viết', $count),
            'Đổi trạng thái hàng loạt sang "' . $newStatus->label() . '"'
        );

        $this->addFlash('success', sprintf('Đã cập nhật %d bài viết sang "%s".', $count, $newStatus->label()));

        return $this->redirectToIndex($request);
    }
}
