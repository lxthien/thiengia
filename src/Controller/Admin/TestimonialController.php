<?php

namespace App\Controller\Admin;

use App\Entity\ActivityLog;
use App\Entity\Testimonial;
use App\Form\TestimonialType;
use App\Service\ActivityLogService;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Controller used to manage testimonials in the backend.
 */
#[Route('/admin/testimonial')]
#[IsGranted('ROLE_EDITOR')]
class TestimonialController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ActivityLogService $activityLogService,
    ) {
    }

    /**
     * Lists all the testimonial entities.
     */
    #[Route('/', name: 'admin_testimonial_index', methods: ['GET'])]
    public function indexAction()
    {
        $testimonials = $this->em->getRepository(Testimonial::class)->findAllOrdered();

        return $this->render('admin/testimonial/index.html.twig', ['objects' => $testimonials]);
    }

    #[Route('/new', name: 'admin_testimonial_new', methods: ['GET', 'POST'])]
    public function newAction(Request $request)
    {
        $testimonial = new Testimonial();

        $form = $this->createForm(TestimonialType::class, $testimonial);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $maxPosition = $this->em->createQuery(
                'SELECT MAX(t.position) FROM App\Entity\Testimonial t'
            )->getSingleScalarResult();
            $testimonial->setPosition(($maxPosition ?? -1) + 1);

            $this->em->persist($testimonial);
            $this->em->flush();

            // Activity Log
            $this->activityLogService->log(
                ActivityLog::ACTION_CREATE,
                ActivityLog::ENTITY_TESTIMONIAL,
                $testimonial->getId(),
                $testimonial->getName()
            );

            $this->addFlash('success', 'action.created_successfully');

            return $this->redirectToRoute('admin_testimonial_index');
        }

        return $this->render('admin/testimonial/new.html.twig', [
            'object' => $testimonial,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_testimonial_edit', methods: ['GET', 'POST'])]
    public function editAction(Request $request, Testimonial $testimonial)
    {
        $form = $this->createForm(TestimonialType::class, $testimonial);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Capture changes before flush
            $diffDetails = $this->activityLogService->getEntityDiff($testimonial);

            $this->em->flush();

            // Activity Log
            $this->activityLogService->log(
                ActivityLog::ACTION_UPDATE,
                ActivityLog::ENTITY_TESTIMONIAL,
                $testimonial->getId(),
                $testimonial->getName(),
                $diffDetails
            );

            $this->addFlash('success', 'action.updated_successfully');

            return $this->redirectToRoute('admin_testimonial_index');
        }

        return $this->render('admin/testimonial/edit.html.twig', [
            'object' => $testimonial,
            'form' => $form->createView(),
        ]);
    }

    /**
     * Deletes a testimonial entity.
     */
    #[Route('/{id}/delete', name: 'admin_testimonial_delete', methods: ['POST'])]
    public function deleteAction(Request $request, Testimonial $testimonial)
    {
        if (!$this->isCsrfTokenValid('delete', $request->request->get('token'))) {
            return $this->redirectToRoute('admin_testimonial_index');
        }

        $testimonialName = $testimonial->getName();
        $testimonialId = $testimonial->getId();

        $this->em->remove($testimonial);
        $this->em->flush();

        // Activity Log
        $this->activityLogService->log(
            ActivityLog::ACTION_DELETE,
            ActivityLog::ENTITY_TESTIMONIAL,
            $testimonialId,
            $testimonialName
        );

        $this->addFlash('success', 'action.deleted_successfully');

        return $this->redirectToRoute('admin_testimonial_index');
    }

    /**
     * Reorders testimonials via AJAX drag-drop.
     */
    #[Route('/reorder', name: 'admin_testimonial_reorder', methods: ['POST'])]
    public function reorderAction(Request $request)
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['items']) || !is_array($data['items'])) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid data format'], 400);
        }

        try {
            foreach ($data['items'] as $position => $id) {
                $testimonial = $this->em->getRepository(Testimonial::class)->find($id);
                if (!$testimonial) {
                    continue;
                }
                $testimonial->setPosition($position);
            }

            $this->em->flush();

            return new JsonResponse(['success' => true, 'message' => 'Testimonial reordered successfully']);
        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }
}
