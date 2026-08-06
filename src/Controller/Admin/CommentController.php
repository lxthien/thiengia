<?php

namespace App\Controller\Admin;

use App\Entity\ActivityLog;
use App\Entity\Comment;
use App\Form\CommentType;
use App\Service\ActivityLogService;
use App\Utils\Slugger;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller used to manage comment in the backend.
 *
 * @Route("/admin/comment")
 * @Security("has_role('ROLE_ADMIN')")
 */

class CommentController extends Controller
{
    /**
     * Lists all Comment entities.
     *
     * @Route("/", name="admin_comment_index")
     * @Method("GET")
     */
    public function indexAction()
    {
        $em = $this->getDoctrine()->getManager();
        $comments = $em->getRepository(Comment::class)->findBy(
            array(),
            array('createdAt' => 'DESC')
        );

        return $this->render('admin/comment/index.html.twig', [
            'objects' => $comments
        ]);
    }

    /**
     * Displays a form to edit an existing Comment entity.
     *
     * @Route("/{id}/edit", requirements={"id": "\d+"}, name="admin_comment_edit")
     * @Method({"GET", "POST"})
     */
    public function editAction(Request $request, Comment $comment, Slugger $slugger)
    {
        //$this->denyAccessUnlessGranted('edit', $category, 'Posts can only be edited by their authors.');

        $form = $this->createForm(CommentType::class, $comment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Capture changes before flush
            $diffDetails = $this->get(ActivityLogService::class)->getEntityDiff($comment);

            $this->getDoctrine()->getManager()->flush();

            // Activity Log
            $this->get(ActivityLogService::class)->log(
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
     *
     * @Route("/{id}/reply", requirements={"id": "\d+"}, name="admin_comment_reply")
     * @Method({"GET", "POST"})
     */
    public function replyAction(Request $request, Comment $comment, Slugger $slugger)
    {
        $replyComment = new Comment();
        $replyComment->setNewsId( $comment->getNewsId() );
        $replyComment->setCommentId( $comment->getId() );
        $replyComment->setEmail( $this->getUser()->getEmail() );
        $replyComment->setApproved( true );
        $replyComment->setAuthor( $this->getUser()->getName() );
        $replyComment->setIp( $this->container->get('request_stack')->getCurrentRequest()->getClientIp() );

        $form = $this->createForm(CommentType::class, $replyComment);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $em = $this->getDoctrine()->getManager();
            $em->persist($replyComment);
            $em->flush();

            // Activity Log
            $this->get(ActivityLogService::class)->log(
                ActivityLog::ACTION_CREATE,
                ActivityLog::ENTITY_COMMENT,
                $replyComment->getId(),
                'Trả lời bình luận của ' . $comment->getAuthor()
            );

            if (!$comment->getApproved()) {
                $comment->setApproved( true );
                
                $em = $this->getDoctrine()->getManager();
                $em->persist($comment);
                $em->flush();
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
     *
     * @Route("/{id}/delete", name="admin_comment_delete")
     * @Method("POST")
     */
    public function deleteAction(Request $request, Comment $comment)
    {
        if (!$this->isCsrfTokenValid('delete', $request->request->get('token'))) {
            return $this->redirectToRoute('admin_comment_index');
        }

        $commentAuthor = $comment->getAuthor();
        $commentId = $comment->getId();

        $em = $this->getDoctrine()->getManager();
        $em->remove($comment);
        $em->flush();

        // Activity Log
        $this->get(ActivityLogService::class)->log(
            ActivityLog::ACTION_DELETE,
            ActivityLog::ENTITY_COMMENT,
            $commentId,
            'Bình luận của ' . $commentAuthor
        );

        $this->addFlash('success', 'action.deleted_successfully');

        return $this->redirectToRoute('admin_comment_index');
    }
}
