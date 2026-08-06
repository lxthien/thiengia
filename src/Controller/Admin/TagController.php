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
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller used to manage tag contents in the backend.
 *
 * @Route("/admin/tag")
 * @Security("has_role('ROLE_ADMIN')")
 */

class TagController extends Controller
{
    /**
     * Lists all Tag entities.
     *
     * @Route("/", name="admin_tag_index")
     * @Method("GET")
     */
    public function indexAction()
    {
        $em = $this->getDoctrine()->getManager();
        $tags = $em->getRepository(Tag::class)->findAll();

        return $this->render('admin/tag/index.html.twig', ['objects' => $tags]);
    }

    /**
     * Displays a form to edit an existing Tag entity.
     *
     * @Route("/{id}/edit", requirements={"id": "\d+"}, name="admin_tag_edit")
     * @Method({"GET", "POST"})
     */
    public function editAction(Request $request, Tag $tag, Slugger $slugger)
    {
        //$this->denyAccessUnlessGranted('edit', $category, 'Posts can only be edited by their authors.');

        $form = $this->createForm(TagType::class, $tag);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Capture changes before flush
            $diffDetails = $this->get(ActivityLogService::class)->getEntityDiff($tag);

            $this->getDoctrine()->getManager()->flush();

            // Activity Log
            $this->get(ActivityLogService::class)->log(
                ActivityLog::ACTION_UPDATE,
                ActivityLog::ENTITY_TAG,
                $tag->getId(),
                $tag->getName(),
                $diffDetails
            );

            $this->addFlash('success', 'updated_successfully');
            return $this->redirectToRoute('admin_tag_index');
        }

        return $this->render('admin/tag/edit.html.twig', [
            'object' => $tag,
            'form' => $form->createView(),
        ]);
    }

    /**
     * Deletes a Tag entity.
     *
     * @Route("/{id}/delete", name="admin_tag_delete")
     * @Method("POST")
     * @Security("is_granted('delete', post)")
     *
     * The Security annotation value is an expression (if it evaluates to false,
     * the authorization mechanism will prevent the user accessing this resource).
     */
    public function deleteAction(Request $request, Tag $tag)
    {
        if (!$this->isCsrfTokenValid('delete', $request->request->get('token'))) {
            return $this->redirectToRoute('admin_tag_index');
        }

        $tagName = $tag->getName();
        $tagId = $tag->getId();

        $em = $this->getDoctrine()->getManager();
        $em->remove($tag);
        $em->flush();

        // Activity Log
        $this->get(ActivityLogService::class)->log(
            ActivityLog::ACTION_DELETE,
            ActivityLog::ENTITY_TAG,
            $tagId,
            $tagName
        );

        $this->addFlash('success', 'deleted_successfully');

        return $this->redirectToRoute('admin_tag_index');
    }
}
