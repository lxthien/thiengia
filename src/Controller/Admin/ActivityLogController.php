<?php

namespace App\Controller\Admin;

use App\Entity\ActivityLog;
use App\Entity\User;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller quản lý Nhật ký hoạt động
 *
 * @Route("/admin/activity-log")
 * @Security("has_role('ROLE_ADMIN')")
 */
class ActivityLogController extends Controller
{
    /**
     * Hiển thị danh sách nhật ký hoạt động
     *
     * @Route("/", name="admin_activity_log_index")
     * @Method("GET")
     */
    public function indexAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $repository = $em->getRepository(ActivityLog::class);

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
        $users = $em->getRepository(User::class)->findBy([], ['username' => 'ASC']);

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
