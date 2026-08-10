<?php

namespace App\Controller\Admin;

use App\Entity\ActivityLog;
use App\Entity\Banner;
use App\Form\BannerType;
use App\Service\ActivityLogService;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Controller used to manage banner in the backend.
 */
#[Route('/admin/banner')]
#[IsGranted('ROLE_EDITOR')]
class BannerController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ActivityLogService $activityLogService,
    ) {
    }

    /**
     * Lists all the banner entities.
     */
    #[Route('/', name: 'admin_banner_index', methods: ['GET'])]
    public function indexAction()
    {
        $banners = $this->em->getRepository(Banner::class)->findAllOrdered();

        return $this->render('admin/banner/index.html.twig', ['objects' => $banners]);
    }

    #[Route('/new', name: 'admin_banner_new', methods: ['GET', 'POST'])]
    public function newAction(Request $request)
    {
        $banner = new Banner();

        $form = $this->createForm(BannerType::class, $banner);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $maxPosition = $this->em->createQuery(
                'SELECT MAX(b.position) FROM App\Entity\Banner b'
            )->getSingleScalarResult();
            $banner->setPosition(($maxPosition ?? -1) + 1);

            $this->em->persist($banner);
            $this->em->flush();

            // Activity Log
            $this->activityLogService->log(
                ActivityLog::ACTION_CREATE,
                ActivityLog::ENTITY_BANNER,
                $banner->getId(),
                $banner->getName()
            );

            $this->addFlash('success', 'action.created_successfully');

            return $this->redirectToRoute('admin_banner_index');
        }

        return $this->render('admin/banner/new.html.twig', [
            'object' => $banner,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_banner_edit', methods: ['GET', 'POST'])]
    public function editAction(Request $request, Banner $banner)
    {
        $form = $this->createForm(BannerType::class, $banner);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Capture changes before flush
            $diffDetails = $this->activityLogService->getEntityDiff($banner);

            $this->em->flush();

            // Activity Log
            $this->activityLogService->log(
                ActivityLog::ACTION_UPDATE,
                ActivityLog::ENTITY_BANNER,
                $banner->getId(),
                $banner->getName(),
                $diffDetails
            );

            $this->addFlash('success', 'action.updated_successfully');

            return $this->redirectToRoute('admin_banner_index');
        }

        return $this->render('admin/banner/edit.html.twig', [
            'object' => $banner,
            'form' => $form->createView(),
        ]);
    }

    /**
     * Deletes a banner entity.
     */
    #[Route('/{id}/delete', name: 'admin_banner_delete', methods: ['POST'])]
    public function deleteAction(Request $request, Banner $banner)
    {
        if (!$this->isCsrfTokenValid('delete', $request->request->get('token'))) {
            return $this->redirectToRoute('admin_banner_index');
        }

        $bannerName = $banner->getName();
        $bannerId = $banner->getId();

        $this->em->remove($banner);
        $this->em->flush();

        // Activity Log
        $this->activityLogService->log(
            ActivityLog::ACTION_DELETE,
            ActivityLog::ENTITY_BANNER,
            $bannerId,
            $bannerName
        );

        $this->addFlash('success', 'action.deleted_successfully');

        return $this->redirectToRoute('admin_banner_index');
    }

    /**
     * Reorders banners via AJAX drag-drop.
     */
    #[Route('/reorder', name: 'admin_banner_reorder', methods: ['POST'])]
    public function reorderAction(Request $request)
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['items']) || !is_array($data['items'])) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid data format'], 400);
        }

        try {
            foreach ($data['items'] as $position => $id) {
                $banner = $this->em->getRepository(Banner::class)->find($id);
                if (!$banner) {
                    continue;
                }
                $banner->setPosition($position);
            }

            $this->em->flush();

            return new JsonResponse(['success' => true, 'message' => 'Banner reordered successfully']);
        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }
}
