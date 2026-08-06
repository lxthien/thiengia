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

        $result = $repository->findByFilters($filters, $page, 30);

        // Get all users for filter dropdown
        $users = $this->em->getRepository(User::class)->findBy([], ['username' => 'ASC']);

        return $this->render('admin/activity_log/index.html.twig', [
            'logs' => $result['items'],
            'total' => $result['total'],
            'pages' => $result['pages'],
            'currentPage' => $result['currentPage'],
            'filters' => $filters,
            'users' => $users,
        ]);
    }
}
