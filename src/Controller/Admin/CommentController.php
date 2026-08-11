<?php

namespace App\Controller\Admin;

use App\Entity\ActivityLog;
use App\Entity\Comment;
use App\Form\CommentType;
use App\Service\ActivityLogService;
use App\Utils\Slugger;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Controller used to manage comment in the backend.
 */
#[Route('/admin/comment')]
#[IsGranted('ROLE_EDITOR')]
class CommentController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ActivityLogService $activityLogService,
        private readonly RequestStack $requestStack,
        private readonly PaginatorInterface $paginator,
    ) {
    }

    /**
     * Lists all Comment entities.
     */
    #[Route('/', name: 'admin_comment_index', methods: ['GET'])]
    public function indexAction(Request $request)
    {
        $q = trim((string) $request->query->get('q', ''));
        $status = (string) $request->query->get('status', '');

        $qb = $this->em->getRepository(Comment::class)->search($q, $status);

        $pagination = $this->paginator->paginate(
            $qb,
            $request->query->getInt('page', 1),
            20
        );

        return $this->render('admin/comment/index.html.twig', [
            'pagination' => $pagination,
            'filters' => [
                'q' => $q,
                'status' => $status,
            ],
        ]);
    }

    /**
     * Displays a form to edit an existing Comment entity.
     */
    #[Route('/{id}/edit', requirements: ['id' => '\d+'], name: 'admin_comment_edit', methods: ['GET', 'POST'])]
    public function editAction(Request $request, Comment $comment, Slugger $slugger)
    {
        //$this->denyAccessUnlessGranted('edit', $category, 'Posts can only be edited by their authors.');

        $form = $this->createForm(CommentType::class, $comment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Capture changes before flush
            $diffDetails = $this->activityLogService->getEntityDiff($comment);

            $this->em->flush();

            // Activity Log
            $this->activityLogService->log(
                ActivityLog::ACTION_UPDATE,
                ActivityLog::ENTITY_COMMENT,
                $comment->getId(),
                'Bình luận của ' . $comment->getAuthor(),
                $diffDetails
            );

            $this->addFlash('success', 'action.updated_successfully');

            return $this->redirectToRoute('admin_comment_index');
        }

        return $this->render('admin/comment/edit.html.twig', [
            'comment' => $comment,
            'form' => $form->createView(),
        ]);
    }

    /**
     * Displays a form to reply an existing Comment entity.
     */
    #[Route('/{id}/reply', requirements: ['id' => '\d+'], name: 'admin_comment_reply', methods: ['GET', 'POST'])]
    public function replyAction(Request $request, Comment $comment, Slugger $slugger)
    {
        $replyComment = new Comment();
        $replyComment->setNews( $comment->getNews() );
        $replyComment->setParent( $comment );
        $replyComment->setEmail( $this->getUser()->getEmail() );
        $replyComment->setApproved( true );
        $replyComment->setAuthor( $this->getUser()->getName() );
        $replyComment->setIp( $this->requestStack->getCurrentRequest()->getClientIp() );

        $form = $this->createForm(CommentType::class, $replyComment);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $this->em->persist($replyComment);
            $this->em->flush();

            // Activity Log
            $this->activityLogService->log(
                ActivityLog::ACTION_CREATE,
                ActivityLog::ENTITY_COMMENT,
                $replyComment->getId(),
                'Trả lời bình luận của ' . $comment->getAuthor()
            );

            if (!$comment->getApproved()) {
                $comment->setApproved( true );

                $this->em->persist($comment);
                $this->em->flush();
            }

            $this->addFlash('success', 'action.updated_successfully');

            return $this->redirectToRoute('admin_comment_index');
        }

        return $this->render('admin/comment/reply.html.twig', [
            'comment' => $replyComment,
            'form' => $form->createView(),
        ]);
    }

    /**
     * Deletes a Comment entity.
     */
    #[Route('/{id}/delete', name: 'admin_comment_delete', methods: ['POST'])]
    public function deleteAction(Request $request, Comment $comment)
    {
        if (!$this->isCsrfTokenValid('delete', $request->request->get('token'))) {
            return $this->redirectToRoute('admin_comment_index');
        }

        $commentAuthor = $comment->getAuthor();
        $commentId = $comment->getId();

        $this->em->remove($comment);
        $this->em->flush();

        // Activity Log
        $this->activityLogService->log(
            ActivityLog::ACTION_DELETE,
            ActivityLog::ENTITY_COMMENT,
            $commentId,
            'Bình luận của ' . $commentAuthor
        );

        $this->addFlash('success', 'action.deleted_successfully');

        return $this->redirectToRoute('admin_comment_index');
    }

    /**
     * Quick-approve a Comment entity.
     */
    #[Route('/{id}/approve', requirements: ['id' => '\d+'], name: 'admin_comment_approve', methods: ['POST'])]
    public function approveAction(Request $request, Comment $comment)
    {
        if (!$this->isCsrfTokenValid('comment_status_' . $comment->getId(), $request->request->get('token'))) {
            return $this->redirectToRoute('admin_comment_index', $request->query->all());
        }

        $comment->setApproved(true);
        $this->em->flush();

        // Activity Log
        $this->activityLogService->log(
            ActivityLog::ACTION_TOGGLE,
            ActivityLog::ENTITY_COMMENT,
            $comment->getId(),
            'Bình luận của ' . $comment->getAuthor(),
            'Đã duyệt'
        );

        $this->addFlash('success', 'Đã duyệt bình luận.');

        return $this->redirectToRoute('admin_comment_index', $request->query->all());
    }

    /**
     * Quick-unapprove a Comment entity.
     */
    #[Route('/{id}/unapprove', requirements: ['id' => '\d+'], name: 'admin_comment_unapprove', methods: ['POST'])]
    public function unapproveAction(Request $request, Comment $comment)
    {
        if (!$this->isCsrfTokenValid('comment_status_' . $comment->getId(), $request->request->get('token'))) {
            return $this->redirectToRoute('admin_comment_index', $request->query->all());
        }

        $comment->setApproved(false);
        $this->em->flush();

        // Activity Log
        $this->activityLogService->log(
            ActivityLog::ACTION_TOGGLE,
            ActivityLog::ENTITY_COMMENT,
            $comment->getId(),
            'Bình luận của ' . $comment->getAuthor(),
            'Chuyển về chờ duyệt'
        );

        $this->addFlash('success', 'Đã chuyển bình luận về chờ duyệt.');

        return $this->redirectToRoute('admin_comment_index', $request->query->all());
    }

    /**
     * Bulk approve/unapprove/delete for a set of Comment entities.
     */
    #[Route('/bulk', name: 'admin_comment_bulk', methods: ['POST'])]
    public function bulkAction(Request $request)
    {
        if (!$this->isCsrfTokenValid('bulk_comment', $request->request->get('token'))) {
            return $this->redirectToRoute('admin_comment_index', $request->query->all());
        }

        $action = $request->request->get('bulk_action');
        $ids = array_filter((array) $request->request->all('ids'), 'is_numeric');

        if (!$ids || !in_array($action, ['approve', 'unapprove', 'delete'], true)) {
            $this->addFlash('warning', 'Vui lòng chọn bình luận và thao tác hợp lệ.');

            return $this->redirectToRoute('admin_comment_index', $request->query->all());
        }

        $comments = $this->em->getRepository(Comment::class)->createQueryBuilder('c')
            ->where('c.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();

        foreach ($comments as $comment) {
            if ($action === 'delete') {
                $this->em->remove($comment);
            } else {
                $comment->setApproved($action === 'approve');
            }
        }

        $this->em->flush();

        // Activity Log — 1 dòng tổng hợp, tránh spam nhật ký khi xử lý hàng loạt
        $this->activityLogService->log(
            $action === 'delete' ? ActivityLog::ACTION_DELETE : ActivityLog::ACTION_TOGGLE,
            ActivityLog::ENTITY_COMMENT,
            null,
            'Xử lý hàng loạt (' . $action . ')',
            count($comments) . ' bình luận'
        );

        $this->addFlash('success', 'Đã xử lý ' . count($comments) . ' bình luận.');

        return $this->redirectToRoute('admin_comment_index', $request->query->all());
    }
}
