<?php

namespace App\Controller\Admin;

use App\Entity\ActivityLog;
use App\Entity\Comment;
use App\Form\CommentType;
use App\Service\ActivityLogService;
use App\Utils\Slugger;
use Doctrine\ORM\EntityManagerInterface;
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
    ) {
    }

    /**
     * Lists all Comment entities.
     */
    #[Route('/', name: 'admin_comment_index', methods: ['GET'])]
    public function indexAction()
    {
        $comments = $this->em->getRepository(Comment::class)->findBy(
            array(),
            array('createdAt' => 'DESC')
        );

        return $this->render('admin/comment/index.html.twig', [
            'objects' => $comments
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
        $replyComment->setNewsId( $comment->getNewsId() );
        $replyComment->setCommentId( $comment->getId() );
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
}
