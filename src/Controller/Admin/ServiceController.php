<?php

namespace App\Controller\Admin;

use App\Entity\ActivityLog;
use App\Entity\Service;
use App\Form\ServiceType;
use App\Repository\ServiceRepository;
use App\Service\ActivityLogService;
use App\Service\Admin\RowReorderService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Quản lý dịch vụ — khối "Dịch vụ" trang chủ và danh sách nhu cầu trong form
 * báo giá nhanh cùng đọc từ đây.
 */
#[Route('/admin/service')]
#[IsGranted('ROLE_EDITOR')]
class ServiceController extends AbstractController
{
    private const NOUN = 'dịch vụ';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ServiceRepository $repository,
        private readonly ActivityLogService $activityLogService,
        private readonly RowReorderService $rowReorder,
    ) {
    }

    #[Route('/', name: 'admin_service_index', methods: ['GET'])]
    public function indexAction(): Response
    {
        return $this->render('admin/service/index.html.twig', [
            'objects' => $this->repository->findAllOrdered(),
        ]);
    }

    #[Route('/new', name: 'admin_service_new', methods: ['GET', 'POST'])]
    public function newAction(Request $request): Response
    {
        return $this->editForm($request, new Service(), true);
    }

    #[Route('/{id}/edit', name: 'admin_service_edit', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    public function editAction(Request $request, Service $service): Response
    {
        return $this->editForm($request, $service, false);
    }

    private function editForm(Request $request, Service $service, bool $new): Response
    {
        $form = $this->createForm(ServiceType::class, $service);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($new) {
                $service->setPosition($this->rowReorder->nextPosition(Service::class));
            }

            $details = $new ? null : $this->activityLogService->getEntityDiff($service);
            $this->em->persist($service);
            $this->em->flush();
            $this->activityLogService->log(
                $new ? ActivityLog::ACTION_CREATE : ActivityLog::ACTION_UPDATE,
                ActivityLog::ENTITY_SERVICE,
                $service->getId(),
                $service->getName(),
                $details,
            );

            $this->addFlash('success', $new ? 'action.created_successfully' : 'action.updated_successfully');

            return $this->redirectToRoute('admin_service_index');
        }

        return $this->render($new ? 'admin/service/new.html.twig' : 'admin/service/edit.html.twig', [
            'object' => $service,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_service_delete', requirements: ['id' => '\\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function deleteAction(Request $request, Service $service): Response
    {
        if (!$this->isCsrfTokenValid('delete_service_' . $service->getId(), $request->request->get('token'))) {
            $this->addFlash('error', 'Phiên làm việc đã hết hạn. Vui lòng thử lại.');

            return $this->redirectToRoute('admin_service_index');
        }

        $id = $service->getId();
        $name = $service->getName();
        $this->em->remove($service);
        $this->em->flush();
        $this->activityLogService->log(ActivityLog::ACTION_DELETE, ActivityLog::ENTITY_SERVICE, $id, $name);
        $this->addFlash('success', 'Đã xóa dịch vụ. Ảnh trong thư viện vẫn được giữ lại.');

        return $this->redirectToRoute('admin_service_index');
    }

    #[Route('/{id}/visibility', name: 'admin_service_visibility', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function visibilityAction(Request $request, Service $service): JsonResponse
    {
        return $this->rowReorder->toggleVisibility(
            $request,
            $service,
            'service_visibility_',
            ActivityLog::ENTITY_SERVICE,
            'Đã bật hiển thị dịch vụ "%s".',
            'Đã ẩn dịch vụ "%s".',
        );
    }

    #[Route('/reorder', name: 'admin_service_reorder', methods: ['POST'])]
    public function reorderAction(Request $request): JsonResponse
    {
        return $this->rowReorder->reorder(
            $request,
            'reorder_services',
            fn (): array => $this->repository->findForReorder(),
            self::NOUN,
            ActivityLog::ENTITY_SERVICE,
            'Thứ tự dịch vụ',
        );
    }
}
