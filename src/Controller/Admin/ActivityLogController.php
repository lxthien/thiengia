<?php

namespace App\Controller\Admin;

use App\Entity\ActivityLog;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Controller quản lý Nhật ký hoạt động
 */
#[Route('/admin/activity-log')]
#[IsGranted('ROLE_ADMIN')]
class ActivityLogController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Hiển thị danh sách nhật ký hoạt động
     */
    #[Route('/', name: 'admin_activity_log_index', methods: ['GET'])]
    public function indexAction(Request $request)
    {
        $repository = $this->em->getRepository(ActivityLog::class);

        // Build filters from query params
        $filters = [
            'userId' => $request->query->get('user'),
            'action' => $request->query->get('action'),
            'entityType' => $request->query->get('entity_type'),
            'dateFrom' => $request->query->get('date_from'),
            'dateTo' => $request->query->get('date_to'),
            'search' => trim((string) $request->query->get('q', '')),
        ];

        $page = max(1, (int) $request->query->get('page', 1));

        $filterError = null;
        foreach (['dateFrom', 'dateTo'] as $key) {
            if ($filters[$key]) {
                $date = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $filters[$key]);
                if (!$date || $date->format('Y-m-d') !== $filters[$key]) {
                    $filters[$key] = null;
                    $filterError = 'Ngày lọc không hợp lệ đã được bỏ qua. Vui lòng chọn lại ngày.';
                }
            }
        }
        if ($filters['dateFrom'] && $filters['dateTo'] && $filters['dateFrom'] > $filters['dateTo']) {
            [$filters['dateFrom'], $filters['dateTo']] = [$filters['dateTo'], $filters['dateFrom']];
            $filterError = 'Đã đổi thứ tự khoảng ngày để ngày bắt đầu không lớn hơn ngày kết thúc.';
        }
        $perPage = $request->query->getInt('per_page', 30);
        $perPage = in_array($perPage, [30, 50, 100], true) ? $perPage : 30;
        $result = $repository->findByFilters($filters, $page, $perPage);

        // Get all users for filter dropdown
        $users = $this->em->getRepository(User::class)->findBy([], ['username' => 'ASC']);

        return $this->render('admin/activity_log/index.html.twig', [
            'logs' => $result['items'],
            'total' => $result['total'],
            'pages' => $result['pages'],
            'currentPage' => $result['currentPage'],
            'filters' => $filters,
            'users' => $users,
            'perPage' => $perPage,
            'filterError' => $filterError,
        ]);
    }
}
