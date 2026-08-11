<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Controller\Admin;

use App\Entity\ActivityLog;
use App\Entity\News;
use App\Entity\Comment;
use App\Entity\User;
use App\Entity\Contact;
use App\Entity\NewsletterSubscriber;
use App\Entity\Banner;
use App\Entity\Tag;
use App\Entity\DailyStats;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Dashboard Controller
 */
#[Route('/admin')]
#[IsGranted('ROLE_AUTHOR')]
class DashboardController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Display dashboard with statistics
     */
    #[Route('/', name: 'admin_dashboard_index', methods: ['GET'])]
    public function indexAction()
    {
        $em = $this->em;

        // Total counts
        $totalPosts = $em->getRepository(News::class)->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $totalComments = $em->getRepository(Comment::class)->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $totalUsers = $em->getRepository(User::class)->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->getQuery()
            ->getSingleScalarResult();

        // Total views
        $viewsData = $em->getRepository(News::class)->createQueryBuilder('n')
            ->select('SUM(n.viewCounts) as totalViews')
            ->getQuery()
            ->getOneOrNullResult();
        $totalViews = $viewsData['totalViews'] ?? 0;

        // Recent posts (last 7 days)
        $recentPosts = $em->getRepository(News::class)->createQueryBuilder('n')
            ->select('n.id, n.title, n.postType, n.createdAt, n.viewCounts')
            ->where('n.createdAt >= :week_ago')
            ->setParameter('week_ago', new \DateTime('-7 days'))
            ->orderBy('n.createdAt', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        // Recent comments
        $recentComments = $em->getRepository(Comment::class)->createQueryBuilder('c')
            ->select('c.id, c.author, c.createdAt, c.approved, IDENTITY(c.news) AS newsId')
            ->orderBy('c.createdAt', 'DESC')
            ->setMaxResults(8)
            ->getQuery()
            ->getResult();

        // View trends - last 30 days
        $viewTrends = $this->getViewTrendsByDate();

        // Approved vs Pending comments
        $approvedComments = $em->getRepository(Comment::class)->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.approved = :approved')
            ->setParameter('approved', true)
            ->getQuery()
            ->getSingleScalarResult();

        $pendingComments = $totalComments - $approvedComments;

        // Top 5 posts by views
        $topPosts = $em->getRepository(News::class)->createQueryBuilder('n')
            ->select('n.id, n.title, n.postType, n.viewCounts')
            ->orderBy('n.viewCounts', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        // New Stats for widgets
        $totalBanners = $em->getRepository(Banner::class)->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $totalTags = $em->getRepository(Tag::class)->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $unreadContacts = $em->getRepository(Contact::class)->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.isRead = :isRead')
            ->setParameter('isRead', false)
            ->getQuery()
            ->getSingleScalarResult();

        $recentContacts = $em->getRepository(Contact::class)->createQueryBuilder('c')
            ->orderBy('c.createdAt', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        // System Information
        $systemInfo = [
            'php_version' => PHP_VERSION,
            'symfony_version' => \Symfony\Component\HttpKernel\Kernel::VERSION,
            'server_os' => PHP_OS,
            'db_driver' => (new \ReflectionClass($em->getConnection()->getDatabasePlatform()))->getShortName(),
        ];

        // Recent activity logs
        $recentActivities = $em->getRepository(ActivityLog::class)->findRecentLogs(10);

        return $this->render('admin/dashboard/index.html.twig', [
            'totalPosts' => $totalPosts,
            'totalComments' => $totalComments,
            'totalUsers' => $totalUsers,
            'totalViews' => $totalViews,
            'totalBanners' => $totalBanners,
            'totalTags' => $totalTags,
            'unreadContacts' => $unreadContacts,
            'recentContacts' => $recentContacts,
            'systemInfo' => $systemInfo,
            'approvedComments' => $approvedComments,
            'pendingComments' => $pendingComments,
            'recentPosts' => $recentPosts,
            'recentComments' => $recentComments,
            'topPosts' => $topPosts,
            'viewTrends' => $viewTrends,
            'recentActivities' => $recentActivities,
        ]);
    }

    #[Route('/notifications/feed', name: 'admin_notifications_feed', methods: ['GET'])]
    public function notificationsFeedAction()
    {
        $notifications = $this->buildAdminNotifications();

        return new JsonResponse([
            'total' => $notifications['total'],
            'html' => $this->renderView('admin/layout/_notifications_menu.html.twig', [
                'notifications' => $notifications,
            ]),
        ]);
    }

    /**
     * Get view trends for last 30 days
     */
    private function getViewTrendsByDate()
    {
        return $this->em->getRepository(DailyStats::class)->getTrends(30);
    }

    private function buildAdminNotifications()
    {
        $contactRepository = $this->em->getRepository(Contact::class);
        $commentRepository = $this->em->getRepository(Comment::class);
        $userRepository = $this->em->getRepository(User::class);
        $newsletterRepository = $this->em->getRepository(NewsletterSubscriber::class);

        $contactCount = $contactRepository->countUnread();
        $commentCount = $commentRepository->countPending();
        $userCount = $userRepository->countUnreadRegistrationNotifications();
        $newsletterCount = $newsletterRepository->countUnread();

        return [
            'total' => $contactCount + $commentCount + $userCount + $newsletterCount,
            'contacts' => [
                'count' => $contactCount,
                'items' => $contactRepository->findUnreadNotifications(),
            ],
            'comments' => [
                'count' => $commentCount,
                'items' => $commentRepository->findPendingNotifications(),
            ],
            'users' => [
                'count' => $userCount,
                'items' => $userRepository->findUnreadRegistrationNotifications(),
            ],
            'newsletter' => [
                'count' => $newsletterCount,
                'items' => $newsletterRepository->findUnreadNotifications(),
            ],
        ];
    }
}
