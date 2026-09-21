<?php

namespace App\Controller\Admin;

use App\Entity\ActivityLog;
use App\Entity\News;
use App\Enum\PostStatus;
use App\Form\PageType;
use App\Service\ActivityLogService;
use App\Utils\Slugger;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Controller used to manage page contents in the backend.
 */
#[Route('/admin/page')]
#[IsGranted('ROLE_EDITOR')]
class PageController extends AbstractController
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
    #[Route('/', name: 'admin_page_index', methods: ['GET'])]
    public function indexAction(Request $request)
    {
        $repository = $this->em->getRepository(News::class);
        $searchQuery = trim((string) $request->query->get('q', ''));
        $pageLevels = [];

        if ($searchQuery !== '') {
            $qb = $repository->searchPages($searchQuery);
        } else {
            // Phân trang theo trang gốc (parent IS NULL) — mỗi trang kết quả vẫn
            // hiển thị đầy đủ cây con của nó (xem _tree_row.html.twig), không cắt
            // ngang quan hệ cha/con.
            $qb = $repository->findPagesAsTree();
        }

        $pagination = $this->paginator->paginate(
            $qb,
            $request->query->getInt('page', 1),
            20
        );

        if ($searchQuery !== '') {
            foreach ($pagination as $page) {
                $pageLevels[$page->getId()] = $this->getPageLevel($page);
            }
        }

        return $this->render('admin/page/index.html.twig', [
            'pagination' => $pagination,
            'page_levels' => $pageLevels,
            'search_query' => $searchQuery,
        ]);
    }

    /**
     * Creates a new News entity.
     */
    #[Route('/new', name: 'admin_page_new', methods: ['GET', 'POST'])]
    public function newAction(Request $request, Slugger $slugger)
    {
        $news = new News();
        $news->setAuthor($this->getUser());
        $news->setPostType('page');

        $form = $this->createForm(PageType::class, $news)
            ->add('save', SubmitType::class)
            ->add('saveAndCreateNew', SubmitType::class);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->em->persist($news);
                $this->em->flush();

                // Activity Log
                $this->activityLogService->log(
                    ActivityLog::ACTION_CREATE,
                    ActivityLog::ENTITY_PAGE,
                    $news->getId(),
                    $news->getTitle()
                );

                $this->addFlash('success', 'action.created_successfully');

                if ($form->get('saveAndCreateNew')->isClicked()) {
                    return $this->redirectToRoute('admin_page_new');
                }

                return $this->redirectToRoute('admin_page_edit', array(
                    'id' => $news->getId()
                ));
            } catch (\Throwable $e) {
                $this->logger->error('Unable to create page.', ['exception' => $e]);
                $message = 'Không thể lưu trang lúc này. Vui lòng thử lại hoặc liên hệ quản trị viên.';
            }

            $this->addFlash('error', $message);
        }

        return $this->render('admin/page/new.html.twig', [
            'object' => $news,
            'form' => $form->createView(),
        ]);
    }

    /**
     * Displays a form to edit an existing News entity.
     */
    #[Route('/{id}/edit', requirements: ['id' => '\d+'], name: 'admin_page_edit', methods: ['GET', 'POST'])]
    public function editAction(Request $request, News $news, Slugger $slugger)
    {
        $form = $this->createForm(PageType::class, $news)
            ->add('save', SubmitType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $unitOfWork = $this->em->getUnitOfWork();
                $originalData = $unitOfWork->getOriginalEntityData($news);

                // Handle postType change logic
                $originalPostType = isset($originalData['postType']) ? $originalData['postType'] : 'page';
                $newPostType = $news->getPostType();

                // From page to post
                if ($originalPostType === 'page' && $newPostType === 'post') {
                    // Remove parent relationship
                    $news->setParent(null);
                }
                // From post to page
                elseif ($originalPostType === 'post' && $newPostType === 'page') {
                    // Remove relationships with categories and tags
                    $news->getCategory()->clear();
                    $news->getTags()->clear();
                    // Ensure parent is null for pages
                    $news->setParent(null);
                }

                // Capture changes before flush
                $diffDetails = $this->activityLogService->getEntityDiff($news);

                $this->em->flush();

                // Activity Log
                $this->activityLogService->log(
                    ActivityLog::ACTION_UPDATE,
                    ActivityLog::ENTITY_PAGE,
                    $news->getId(),
                    $news->getTitle(),
                    $diffDetails
                );

                $this->addFlash('success', 'action.updated_successfully');

                // If postType changed to post, redirect to news edit
                if ($originalPostType === 'page' && $newPostType === 'post') {
                    return $this->redirectToRoute('admin_news_edit', array(
                        'id' => $news->getId()
                    ));
                }

                return $this->redirectToRoute('admin_page_edit', array(
                    'id' => $news->getId()
                ));
            } catch (\Throwable $e) {
                $this->logger->error('Unable to update page.', ['exception' => $e, 'pageId' => $news->getId()]);
                $message = 'Không thể cập nhật trang lúc này. Vui lòng thử lại hoặc liên hệ quản trị viên.';
            }

            $this->addFlash('error', $message);

            return $this->render('admin/page/edit.html.twig', [
                'object' => $news,
                'form' => $form->createView(),
            ]);
        }

        return $this->render('admin/page/edit.html.twig', [
            'object' => $news,
            'form' => $form->createView(),
        ]);
    }

    /**
     * Deletes a News entity.
     */
    #[Route('/{id}/delete', name: 'admin_page_delete', methods: ['POST'])]
    public function deleteAction(Request $request, $id, News $page)
    {
        if (!$this->isCsrfTokenValid('delete', $request->request->get('token'))) {
            return $this->redirectToRoute('admin_page_index');
        }

        $pageTitle = $page->getTitle();
        $pageId = $page->getId();

        $this->em->remove($page);
        $this->em->flush();

        // Activity Log
        $this->activityLogService->log(
            ActivityLog::ACTION_DELETE,
            ActivityLog::ENTITY_PAGE,
            $pageId,
            $pageTitle
        );

        $this->addFlash('success', 'action.deleted_successfully');

        return $this->redirectToRoute('admin_page_index');
    }

    /**
     * Đổi trạng thái hàng loạt từ danh sách admin — xem NewsController::bulkAction
     * cho lý do thay công tắc bật/tắt cũ (5 trạng thái không hợp để diễn đạt
     * bằng 1 công tắc on/off).
     */
    #[Route('/bulk', name: 'admin_page_bulk', methods: ['POST'])]
    public function bulkAction(Request $request)
    {
        if (!$this->isCsrfTokenValid('bulk_page', $request->request->get('token'))) {
            $this->addFlash('error', 'Phiên làm việc đã hết hạn, vui lòng thử lại.');
            return $this->redirectToRoute('admin_page_index');
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
            $this->addFlash('error', 'Vui lòng chọn trang và thao tác hợp lệ.');
            return $this->redirectToRoute('admin_page_index');
        }

        $newStatus = $statusMap[$bulkAction];
        $pages = $this->em->getRepository(News::class)->findBy(['id' => $ids, 'postType' => 'page']);

        foreach ($pages as $page) {
            $page->setStatus($newStatus);
        }

        $this->em->flush();

        $this->activityLogService->log(
            ActivityLog::ACTION_TOGGLE,
            ActivityLog::ENTITY_PAGE,
            null,
            sprintf('%d trang', count($pages)),
            'Đổi trạng thái hàng loạt sang "' . $newStatus->label() . '"'
        );

        $this->addFlash('success', sprintf('Đã cập nhật %d trang sang "%s".', count($pages), $newStatus->label()));

        return $this->redirectToRoute('admin_page_index');
    }

    private function getPageLevel(News $page)
    {
        $level = 0;
        $currentParent = $page->getParent();

        while (null !== $currentParent) {
            ++$level;
            $currentParent = $currentParent->getParent();
        }

        return $level;
    }
}
