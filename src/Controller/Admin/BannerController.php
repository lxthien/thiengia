<?php

namespace App\Controller\Admin;

use App\Entity\ActivityLog;
use App\Entity\Banner;
use App\Entity\BannerCategory;
use App\Form\BannerType;
use App\Service\ActivityLogService;
use App\Service\BannerOrderValidator;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/banner')]
#[IsGranted('ROLE_EDITOR')]
class BannerController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ActivityLogService $activityLogService,
        private readonly BannerOrderValidator $orderValidator,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('/', name: 'admin_banner_index', methods: ['GET'])]
    public function indexAction(Request $request): Response
    {
        $zone = $request->query->get('zone', BannerCategory::ZONE_HERO);
        if (!isset(BannerCategory::ZONES[$zone])) {
            throw $this->createNotFoundException('Vị trí hiển thị không tồn tại.');
        }
        $category = (string) $request->query->get('category', '');
        $query = mb_substr(trim((string) $request->query->get('q', '')), 0, 150);
        $status = (string) $request->query->get('status', '');
        if (!in_array($status, ['', 'visible', 'hidden'], true)) {
            $status = '';
        }
        $categories = $this->em->getRepository(BannerCategory::class)->findBy(['zone' => $zone], ['name' => 'ASC', 'id' => 'ASC']);
        $selected = null;
        $counts = [];
        foreach ($categories as $group) {
            $counts[$group->getId()] = 0;
            if ((string) $group->getId() === $category) {
                $selected = $group;
            }
        }
        if ($category !== '' && $category !== 'none' && !$selected) {
            throw $this->createNotFoundException('Nhóm banner không thuộc vị trí đang chọn.');
        }
        $all = $this->em->getRepository(Banner::class)->findAllOrdered();
        $zoneBanners = [];
        $unassigned = [];
        foreach ($all as $banner) {
            $group = $banner->getBannerCategory();
            if (!$group) {
                $unassigned[] = $banner;
            } elseif ($group->getZone() === $zone) {
                $zoneBanners[] = $banner;
                ++$counts[$group->getId()];
            }
        }
        $objects = array_values(array_filter($category === 'none' ? $unassigned : $zoneBanners,
            static function (Banner $banner) use ($category, $query, $status): bool {
                if ($category !== '' && $category !== 'none' && (string) $banner->getBannerCategory()?->getId() !== $category) return false;
                if ($status === 'visible' && !$banner->getEnable()) return false;
                if ($status === 'hidden' && $banner->getEnable()) return false;
                return $query === '' || mb_stripos((string) $banner->getName(), $query) !== false;
            }
        ));

        return $this->render('admin/banner/index.html.twig', [
            'objects' => $objects, 'categories' => $categories, 'category_counts' => $counts,
            'selected_category' => $selected, 'category_filter' => $category,
            'zone' => $zone, 'zones' => BannerCategory::ZONES, 'query' => $query, 'status_filter' => $status,
            'zone_count' => count($zoneBanners), 'unassigned_count' => count($unassigned),
            'reorder_enabled' => $category === '' && $query === '' && $status === '',
        ]);
    }

    #[Route('/new', name: 'admin_banner_new', methods: ['GET', 'POST'])]
    public function newAction(Request $request): Response
    {
        $banner = new Banner();
        $categoryId = $request->query->getInt('category');
        if ($categoryId > 0) {
            $group = $this->em->find(BannerCategory::class, $categoryId);
            if (!$group) throw $this->createNotFoundException('Nhóm banner không tồn tại.');
            $banner->setBannerCategory($group);
        }
        return $this->editForm($request, $banner, true);
    }

    #[Route('/{id}/edit', name: 'admin_banner_edit', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    public function editAction(Request $request, Banner $banner): Response
    {
        return $this->editForm($request, $banner, false);
    }

    private function editForm(Request $request, Banner $banner, bool $new): Response
    {
        $panel = $request->query->getBoolean('_panel');
        $oldZone = $banner->getBannerCategory()?->getZone();
        $route = $new ? 'admin_banner_new' : 'admin_banner_edit';
        $parameters = $new ? [] : ['id' => $banner->getId()];
        if ($panel) $parameters['_panel'] = 1;
        $form = $this->createForm(BannerType::class, $banner, ['action' => $this->generateUrl($route, $parameters)]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if ($new || $oldZone !== $banner->getBannerCategory()?->getZone()) {
                $max = $this->em->createQuery('SELECT MAX(b.position) FROM App\\Entity\\Banner b')->getSingleScalarResult();
                $banner->setPosition(($max ?? -1) + 1);
            }
            $details = $new ? null : $this->activityLogService->getEntityDiff($banner);
            $this->em->persist($banner);
            $this->em->flush();
            $this->activityLogService->log($new ? ActivityLog::ACTION_CREATE : ActivityLog::ACTION_UPDATE,
                ActivityLog::ENTITY_BANNER, $banner->getId(), $banner->getName(), $details);
            if ($panel) return $this->json(['success' => true, 'message' => $new ? 'Đã thêm banner.' : 'Đã lưu banner.']);
            $this->addFlash('success', $new ? 'action.created_successfully' : 'action.updated_successfully');
            return $this->redirectToRoute('admin_banner_index', ['category' => $banner->getBannerCategory()?->getId(), 'zone' => $banner->getBannerCategory()?->getZone()]);
        }
        return $this->render($panel ? 'admin/banner/_panel_form.html.twig' : ($new ? 'admin/banner/new.html.twig' : 'admin/banner/edit.html.twig'), [
            'object' => $banner, 'form' => $form->createView(), 'panel_title' => $new ? 'Thêm banner' : 'Chỉnh sửa banner',
        ], new Response(null, $form->isSubmitted() ? 422 : 200));
    }

    #[Route('/{id}/delete', name: 'admin_banner_delete', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function deleteAction(Request $request, Banner $banner): Response
    {
        if (!$this->isCsrfTokenValid('delete', $request->request->get('token'))) {
            return $this->json(['success' => false, 'message' => 'Phiên làm việc đã hết hạn. Vui lòng tải lại trang.'], 403);
        }
        $name = $banner->getName();
        $id = $banner->getId();
        $this->em->remove($banner);
        $this->em->flush();
        $this->activityLogService->log(ActivityLog::ACTION_DELETE, ActivityLog::ENTITY_BANNER, $id, $name);
        if ($request->isXmlHttpRequest()) return $this->json(['success' => true, 'message' => 'Đã xóa banner. Ảnh trong thư viện vẫn được giữ lại.']);
        $this->addFlash('success', 'action.deleted_successfully');
        return $this->redirectToRoute('admin_banner_index');
    }

    #[Route('/{id}/visibility', name: 'admin_banner_visibility', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function visibilityAction(Request $request, Banner $banner): JsonResponse
    {
        if (!$this->isCsrfTokenValid('banner_visibility_' . $banner->getId(), $request->request->get('token'))) {
            return $this->json(['success' => false, 'message' => 'Phiên làm việc đã hết hạn. Vui lòng tải lại trang.'], 403);
        }
        $enable = $request->request->get('enable');
        if (!in_array($enable, ['0', '1'], true)) {
            return $this->json(['success' => false, 'message' => 'Trạng thái không hợp lệ.'], 400);
        }
        $banner->setEnable($enable === '1');
        $this->em->flush();
        $this->activityLogService->log(ActivityLog::ACTION_TOGGLE, ActivityLog::ENTITY_BANNER, $banner->getId(), $banner->getName(), 'enable: ' . $enable);
        return $this->json(['success' => true, 'message' => $banner->getEnable() ? 'Đã bật hiển thị banner.' : 'Đã ẩn banner.']);
    }

    #[Route('/reorder', name: 'admin_banner_reorder', methods: ['POST'])]
    public function reorderAction(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data) || !is_string($data['token'] ?? null) || !$this->isCsrfTokenValid('reorder_banners', $data['token'])) {
            return $this->json(['success' => false, 'message' => 'Phiên làm việc đã hết hạn. Vui lòng tải lại trang.'], 403);
        }
        $zone = $data['zone'] ?? null;
        if (!is_string($zone) || !isset(BannerCategory::ZONES[$zone])) {
            return $this->json(['success' => false, 'message' => 'Vị trí hiển thị không hợp lệ.'], 400);
        }
        $connection = $this->em->getConnection();
        $connection->beginTransaction();
        try {
            $rows = $this->em->getRepository(Banner::class)->findForZone($zone, true);
            $current = array_map(static fn (Banner $banner): int => $banner->getId(), $rows);
            $this->orderValidator->validate($data['items'] ?? null, $data['expected'] ?? null, $current);
            $positions = array_flip($data['items']);
            foreach ($rows as $banner) $banner->setPosition($positions[$banner->getId()]);
            $this->em->flush();
            $connection->commit();
        } catch (\Throwable $exception) {
            if ($connection->isTransactionActive()) $connection->rollBack();
            if ($exception instanceof \InvalidArgumentException || $exception instanceof \LogicException) {
                return $this->json(['success' => false, 'message' => $exception->getMessage()], $exception instanceof \InvalidArgumentException ? 400 : 409);
            }
            $this->logger->error('Banner reordering failed.', ['exception' => $exception]);
            return $this->json(['success' => false, 'message' => 'Không lưu được thứ tự. Vui lòng tải lại trang và thử lại.'], 500);
        }
        $this->activityLogService->log(ActivityLog::ACTION_UPDATE, ActivityLog::ENTITY_BANNER, null, 'Thứ tự banner',
            json_encode(['zone' => $zone, 'items' => $data['items']], JSON_UNESCAPED_UNICODE));
        return $this->json(['success' => true, 'message' => 'Đã lưu thứ tự hiển thị.']);
    }
}
