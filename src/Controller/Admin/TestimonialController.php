<?php

namespace App\Controller\Admin;

use App\Entity\ActivityLog;
use App\Entity\Testimonial;
use App\Form\TestimonialType;
use App\Service\ActivityLogService;
use App\Service\TestimonialOrderValidator;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/testimonial')]
#[IsGranted('ROLE_EDITOR')]
class TestimonialController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ActivityLogService $activityLogService,
        private readonly TestimonialOrderValidator $orderValidator,
        private readonly LoggerInterface $logger,
    ) {}

    #[Route('/', name: 'admin_testimonial_index', methods: ['GET'])]
    public function indexAction(Request $request): Response
    {
        $all = $this->em->getRepository(Testimonial::class)->findAllOrdered();
        $query = mb_substr(trim((string) $request->query->get('q', '')), 0, 150);
        $status = (string) $request->query->get('status', '');
        if (!in_array($status, ['', 'visible', 'hidden'], true)) $status = '';
        $rating = (string) $request->query->get('rating', '');
        if (!in_array($rating, ['', '1', '2', '3', '4', '5'], true)) $rating = '';
        $objects = array_values(array_filter($all, static function (Testimonial $item) use ($query, $status, $rating): bool {
            if ($status === 'visible' && !$item->getEnable()) return false;
            if ($status === 'hidden' && $item->getEnable()) return false;
            if ($rating !== '' && $item->getRating() !== (int) $rating) return false;
            return $query === '' || mb_stripos($item->getName() . ' ' . $item->getRole() . ' ' . $item->getText(), $query) !== false;
        }));
        return $this->render('admin/testimonial/index.html.twig', [
            'objects' => $objects, 'total_count' => count($all), 'query' => $query,
            'status_filter' => $status, 'rating_filter' => $rating,
            'reorder_enabled' => $query === '' && $status === '' && $rating === '',
        ]);
    }

    #[Route('/new', name: 'admin_testimonial_new', methods: ['GET', 'POST'])]
    public function newAction(Request $request): Response
    {
        return $this->editForm($request, new Testimonial(), true);
    }

    #[Route('/{id}/edit', name: 'admin_testimonial_edit', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    public function editAction(Request $request, Testimonial $testimonial): Response
    {
        return $this->editForm($request, $testimonial, false);
    }

    private function editForm(Request $request, Testimonial $testimonial, bool $new): Response
    {
        $panel = $request->query->getBoolean('_panel');
        $parameters = $new ? [] : ['id' => $testimonial->getId()];
        if ($panel) $parameters['_panel'] = 1;
        $form = $this->createForm(TestimonialType::class, $testimonial, [
            'action' => $this->generateUrl($new ? 'admin_testimonial_new' : 'admin_testimonial_edit', $parameters),
        ]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if ($new) {
                $max = $this->em->createQuery('SELECT MAX(t.position) FROM App\\Entity\\Testimonial t')->getSingleScalarResult();
                $testimonial->setPosition(($max ?? -1) + 1);
            }
            $details = $new ? null : $this->activityLogService->getEntityDiff($testimonial);
            $this->em->persist($testimonial);
            $this->em->flush();
            $this->activityLogService->log($new ? ActivityLog::ACTION_CREATE : ActivityLog::ACTION_UPDATE,
                ActivityLog::ENTITY_TESTIMONIAL, $testimonial->getId(), $testimonial->getName(), $details);
            if ($panel) return $this->json(['success' => true, 'message' => $new ? 'Đã thêm đánh giá khách hàng.' : 'Đã lưu đánh giá khách hàng.']);
            $this->addFlash('success', $new ? 'action.created_successfully' : 'action.updated_successfully');
            return $this->redirectToRoute('admin_testimonial_index');
        }
        return $this->render($panel ? 'admin/testimonial/_panel_form.html.twig' : ($new ? 'admin/testimonial/new.html.twig' : 'admin/testimonial/edit.html.twig'), [
            'object' => $testimonial, 'form' => $form->createView(),
            'panel_title' => $new ? 'Thêm đánh giá' : 'Chỉnh sửa đánh giá',
        ], new Response(null, $form->isSubmitted() ? 422 : 200));
    }

    #[Route('/{id}/delete', name: 'admin_testimonial_delete', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function deleteAction(Request $request, Testimonial $testimonial): Response
    {
        if (!$this->isCsrfTokenValid('delete', $request->request->get('token'))) {
            return $this->json(['success' => false, 'message' => 'Phiên làm việc đã hết hạn. Vui lòng tải lại trang.'], 403);
        }
        $id = $testimonial->getId(); $name = $testimonial->getName();
        $this->em->remove($testimonial); $this->em->flush();
        $this->activityLogService->log(ActivityLog::ACTION_DELETE, ActivityLog::ENTITY_TESTIMONIAL, $id, $name);
        if ($request->isXmlHttpRequest()) return $this->json(['success' => true, 'message' => 'Đã xóa đánh giá. Ảnh trong thư viện vẫn được giữ lại.']);
        $this->addFlash('success', 'action.deleted_successfully');
        return $this->redirectToRoute('admin_testimonial_index');
    }

    #[Route('/{id}/visibility', name: 'admin_testimonial_visibility', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function visibilityAction(Request $request, Testimonial $testimonial): JsonResponse
    {
        if (!$this->isCsrfTokenValid('testimonial_visibility_' . $testimonial->getId(), $request->request->get('token'))) {
            return $this->json(['success' => false, 'message' => 'Phiên làm việc đã hết hạn. Vui lòng tải lại trang.'], 403);
        }
        $enable = $request->request->get('enable');
        if (!in_array($enable, ['0', '1'], true)) return $this->json(['success' => false, 'message' => 'Trạng thái không hợp lệ.'], 400);
        $testimonial->setEnable($enable === '1'); $this->em->flush();
        $this->activityLogService->log(ActivityLog::ACTION_TOGGLE, ActivityLog::ENTITY_TESTIMONIAL, $testimonial->getId(), $testimonial->getName(), 'enable: ' . $enable);
        return $this->json(['success' => true, 'message' => $testimonial->getEnable() ? 'Đã bật hiển thị đánh giá.' : 'Đã ẩn đánh giá.']);
    }

    #[Route('/reorder', name: 'admin_testimonial_reorder', methods: ['POST'])]
    public function reorderAction(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data) || !is_string($data['token'] ?? null) || !$this->isCsrfTokenValid('reorder_testimonials', $data['token'])) {
            return $this->json(['success' => false, 'message' => 'Phiên làm việc đã hết hạn. Vui lòng tải lại trang.'], 403);
        }
        $connection = $this->em->getConnection(); $connection->beginTransaction();
        try {
            $rows = $this->em->getRepository(Testimonial::class)->findForReorder();
            $current = array_map(static fn (Testimonial $item): int => $item->getId(), $rows);
            $this->orderValidator->validate($data['items'] ?? null, $data['expected'] ?? null, $current);
            $positions = array_flip($data['items']);
            foreach ($rows as $item) $item->setPosition($positions[$item->getId()]);
            $this->em->flush(); $connection->commit();
        } catch (\Throwable $exception) {
            if ($connection->isTransactionActive()) $connection->rollBack();
            if ($exception instanceof \InvalidArgumentException || $exception instanceof \DomainException) {
                return $this->json(['success' => false, 'message' => $exception->getMessage()], $exception instanceof \DomainException ? 409 : 400);
            }
            $this->logger->error('Testimonial reordering failed.', ['exception' => $exception]);
            return $this->json(['success' => false, 'message' => 'Không lưu được thứ tự. Vui lòng tải lại trang.'], 500);
        }
        $this->activityLogService->log(ActivityLog::ACTION_UPDATE, ActivityLog::ENTITY_TESTIMONIAL, null, 'Thứ tự đánh giá', json_encode($data['items']));
        return $this->json(['success' => true, 'message' => 'Đã lưu thứ tự đánh giá.']);
    }
}
