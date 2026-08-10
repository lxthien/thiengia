<?php

namespace App\Controller\Admin;

use App\Entity\ActivityLog;
use App\Entity\BannerCategory;
use App\Form\BannerCategoryType;
use App\Service\ActivityLogService;

use App\Utils\Slugger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Controller used to manage the banner category in the backend.
 */
#[Route('/admin/bannercategory')]
#[IsGranted('ROLE_EDITOR')]
class BannerCategoryController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ActivityLogService $activityLogService,
    ) {
    }

    /**
     * Lists all the banner categories entities.
     */
    #[Route('/', name: 'admin_bannercategory_index', methods: ['GET'])]
    public function indexAction()
    {
        $bannercategories = $this->em->getRepository(BannerCategory::class)->findAll();

        return $this->render('admin/bannercategory/index.html.twig', ['objects' => $bannercategories]);
    }

    #[Route('/new', name: 'admin_bannercategory_new', methods: ['GET', 'POST'])]
    public function bannerCategoryNewAction(Request $request, Slugger $slugger)
    {
        $bannerCategory = new BannerCategory();

        $form = $this->createForm(BannerCategoryType::class, $bannerCategory);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $this->em->persist($bannerCategory);
            $this->em->flush();

            // Activity Log
            $this->activityLogService->log(
                ActivityLog::ACTION_CREATE,
                ActivityLog::ENTITY_BANNER_CATEGORY,
                $bannerCategory->getId(),
                $bannerCategory->getName()
            );

            $this->addFlash('success', 'action.created_successfully');

            return $this->redirectToRoute('admin_bannercategory_index');
        }

        return $this->render('admin/bannercategory/new.html.twig', [
            'object' => $bannerCategory,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_bannercategory_edit', methods: ['GET', 'POST'])]
    public function bannerCategoryEditAction(Request $request, BannerCategory $bannerCategory, Slugger $slugger)
    {
        $form = $this->createForm(BannerCategoryType::class, $bannerCategory);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $this->em->flush();

            // Activity Log
            $this->activityLogService->log(
                ActivityLog::ACTION_UPDATE,
                ActivityLog::ENTITY_BANNER_CATEGORY,
                $bannerCategory->getId(),
                $bannerCategory->getName()
            );

            $this->addFlash('success', 'action.updated_successfully');

            return $this->redirectToRoute('admin_bannercategory_index');
        }

        return $this->render('admin/bannercategory/edit.html.twig', [
            'object' => $bannerCategory,
            'form' => $form->createView(),
        ]);
    }

    /**
     * Deletes a banner category entity.
     */
    #[Route('/{id}/delete', name: 'admin_bannercategory_delete', methods: ['POST'])]
    public function deleteAction(Request $request, BannerCategory $bannerCategory)
    {
        if (!$this->isCsrfTokenValid('delete', $request->request->get('token'))) {
            return $this->redirectToRoute('admin_bannercategory_index');
        }

        $catName = $bannerCategory->getName();
        $catId = $bannerCategory->getId();

        $this->em->remove($bannerCategory);
        $this->em->flush();

        // Activity Log
        $this->activityLogService->log(
            ActivityLog::ACTION_DELETE,
            ActivityLog::ENTITY_BANNER_CATEGORY,
            $catId,
            $catName
        );

        $this->addFlash('success', 'action.deleted_successfully');

        return $this->redirectToRoute('admin_bannercategory_index');
    }
}
