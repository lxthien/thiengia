<?php

namespace App\Controller\Admin;

use App\Entity\ActivityLog;
use App\Entity\Banner;
use App\Entity\BannerCategory;
use App\Form\BannerCategoryType;
use App\Service\ActivityLogService;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/bannercategory')]
#[IsGranted('ROLE_EDITOR')]
class BannerCategoryController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ActivityLogService $activityLogService,
    ) {
    }

    #[Route('/', name: 'admin_bannercategory_index', methods: ['GET'])]
    public function indexAction(): Response
    {
        return $this->redirectToRoute('admin_banner_index');
    }

    #[Route('/new', name: 'admin_bannercategory_new', methods: ['GET', 'POST'])]
    public function bannerCategoryNewAction(Request $request): Response
    {
        $category = new BannerCategory();
        $zone = $request->query->get('zone', BannerCategory::ZONE_HERO);
        if (isset(BannerCategory::ZONES[$zone])) $category->setZone($zone);
        return $this->editForm($request, $category, true);
    }

    #[Route('/{id}/edit', name: 'admin_bannercategory_edit', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    public function bannerCategoryEditAction(Request $request, BannerCategory $bannerCategory): Response
    {
        return $this->editForm($request, $bannerCategory, false);
    }

    private function editForm(Request $request, BannerCategory $category, bool $new): Response
    {
        $panel = $request->query->getBoolean('_panel');
        $parameters = $new ? [] : ['id' => $category->getId()];
        if ($panel) $parameters['_panel'] = 1;
        $form = $this->createForm(BannerCategoryType::class, $category, [
            'action' => $this->generateUrl($new ? 'admin_bannercategory_new' : 'admin_bannercategory_edit', $parameters),
        ]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $details = $new ? null : $this->activityLogService->getEntityDiff($category);
            $this->em->persist($category);
            $this->em->flush();
            $this->activityLogService->log($new ? ActivityLog::ACTION_CREATE : ActivityLog::ACTION_UPDATE,
                ActivityLog::ENTITY_BANNER_CATEGORY, $category->getId(), $category->getName(), $details);
            if ($panel) return $this->json([
                'success' => true, 'message' => $new ? 'Đã thêm nhóm banner.' : 'Đã lưu nhóm banner.',
                'selectCategory' => $category->getId(), 'zone' => $category->getZone(),
            ]);
            $this->addFlash('success', $new ? 'action.created_successfully' : 'action.updated_successfully');
            return $this->redirectToRoute('admin_banner_index', ['category' => $category->getId(), 'zone' => $category->getZone()]);
        }
        return $this->render($panel ? 'admin/banner/_category_form.html.twig' : ($new ? 'admin/bannercategory/new.html.twig' : 'admin/bannercategory/edit.html.twig'), [
            'object' => $category, 'form' => $form->createView(), 'panel_title' => $new ? 'Thêm nhóm banner' : 'Chỉnh sửa nhóm banner',
        ], new Response(null, $form->isSubmitted() ? 422 : 200));
    }

    #[Route('/{id}/delete', name: 'admin_bannercategory_delete', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function deleteAction(Request $request, BannerCategory $bannerCategory): Response
    {
        if (!$this->isCsrfTokenValid('delete', $request->request->get('token'))) {
            return $this->json(['success' => false, 'message' => 'Phiên làm việc đã hết hạn. Vui lòng tải lại trang.'], 403);
        }
        $id = $bannerCategory->getId();
        $zone = $bannerCategory->getZone();
        $count = $this->em->getRepository(Banner::class)->count(['bannercategory' => $bannerCategory]);
        if ($count > 0) {
            $message = 'Nhóm còn ' . $count . ' banner. Hãy chuyển các banner sang nhóm khác trước khi xóa nhóm.';
            if ($request->isXmlHttpRequest()) return $this->json(['success' => false, 'message' => $message], 409);
            $this->addFlash('error', $message);
            return $this->redirectToRoute('admin_banner_index', ['category' => $id, 'zone' => $zone]);
        }
        $name = $bannerCategory->getName();
        try {
            $this->em->remove($bannerCategory);
            $this->em->flush();
        } catch (ForeignKeyConstraintViolationException $exception) {
            // Another editor may have attached a banner after the count check.
            return $this->json(['success' => false, 'message' => 'Nhóm đang có banner sử dụng. Vui lòng tải lại trang.'], 409);
        }
        $this->activityLogService->log(ActivityLog::ACTION_DELETE, ActivityLog::ENTITY_BANNER_CATEGORY, $id, $name);
        if ($request->isXmlHttpRequest()) return $this->json(['success' => true, 'message' => 'Đã xóa nhóm banner.', 'deletedCategory' => $id]);
        $this->addFlash('success', 'action.deleted_successfully');
        return $this->redirectToRoute('admin_banner_index', ['zone' => $zone]);
    }
}
