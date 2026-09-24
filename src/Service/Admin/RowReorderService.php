<?php

namespace App\Service\Admin;

use App\Entity\ActivityLog;
use App\Entity\Contract\SortableRow;
use App\Service\ActivityLogService;
use App\Service\PositionOrderValidator;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Phần việc giống nhau của mọi màn hình quản lý có kéo-thả: kiểm CSRF, chạy
 * trong transaction, chặn payload cũ/thiếu, ghi nhật ký hoạt động.
 *
 * Controller chỉ còn khai báo route và nói rõ mình quản lý loại dữ liệu nào.
 */
final class RowReorderService
{
    private const EXPIRED = 'Phiên làm việc đã hết hạn. Vui lòng tải lại trang.';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PositionOrderValidator $orderValidator,
        private readonly ActivityLogService $activityLogService,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param \Closure(): array<SortableRow> $fetchRows phải khóa hàng để đọc
     *                                                  (repository::findForReorder)
     */
    public function reorder(
        Request $request,
        string $csrfId,
        \Closure $fetchRows,
        string $noun,
        string $entityType,
        string $logTitle,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (!is_array($data) || !is_string($data['token'] ?? null) || !$this->isTokenValid($csrfId, $data['token'])) {
            return new JsonResponse(['success' => false, 'message' => self::EXPIRED], 403);
        }

        $connection = $this->em->getConnection();
        $connection->beginTransaction();

        try {
            $rows = $fetchRows();
            $current = array_map(static fn (SortableRow $row): int => $row->getId(), $rows);
            $this->orderValidator->validate($data['items'] ?? null, $data['expected'] ?? null, $current, $noun);
            $positions = array_flip($data['items']);

            foreach ($rows as $row) {
                $row->setPosition($positions[$row->getId()]);
            }

            $this->em->flush();
            $connection->commit();
        } catch (\Throwable $exception) {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }

            if ($exception instanceof \InvalidArgumentException || $exception instanceof \DomainException) {
                return new JsonResponse(
                    ['success' => false, 'message' => $exception->getMessage()],
                    $exception instanceof \DomainException ? 409 : 400,
                );
            }

            $this->logger->error('Row reordering failed.', ['entity' => $entityType, 'exception' => $exception]);

            return new JsonResponse(['success' => false, 'message' => 'Không lưu được thứ tự. Vui lòng tải lại trang.'], 500);
        }

        $this->activityLogService->log(
            ActivityLog::ACTION_UPDATE,
            $entityType,
            null,
            $logTitle,
            json_encode($data['items']),
        );

        return new JsonResponse(['success' => true, 'message' => sprintf('Đã lưu thứ tự %s.', $noun)]);
    }

    public function toggleVisibility(
        Request $request,
        SortableRow $row,
        string $csrfIdPrefix,
        string $entityType,
        string $shownMessage,
        string $hiddenMessage,
    ): JsonResponse {
        if (!$this->isTokenValid($csrfIdPrefix . $row->getId(), (string) $request->request->get('token'))) {
            return new JsonResponse(['success' => false, 'message' => self::EXPIRED], 403);
        }

        $enable = $request->request->get('enable');

        if (!in_array($enable, ['0', '1'], true)) {
            return new JsonResponse(['success' => false, 'message' => 'Trạng thái không hợp lệ.'], 400);
        }

        $row->setEnable($enable === '1');
        $this->em->flush();
        $this->activityLogService->log(
            ActivityLog::ACTION_TOGGLE,
            $entityType,
            $row->getId(),
            $row->getName(),
            'enable: ' . $enable,
        );

        return new JsonResponse([
            'success' => true,
            'message' => sprintf($row->getEnable() ? $shownMessage : $hiddenMessage, (string) $row->getName()),
        ]);
    }

    /**
     * Vị trí kế tiếp khi thêm mục mới — luôn xuống cuối danh sách.
     *
     * @param class-string $entityClass
     */
    public function nextPosition(string $entityClass): int
    {
        $max = $this->em->createQuery(sprintf('SELECT MAX(e.position) FROM %s e', $entityClass))->getSingleScalarResult();

        return (int) $max + 1;
    }

    private function isTokenValid(string $id, string $value): bool
    {
        return $this->csrfTokenManager->isTokenValid(new CsrfToken($id, $value));
    }
}
