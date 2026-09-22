<?php

namespace App\Controller\Admin;

use App\Entity\ActivityLog;
use App\Entity\NewsletterSubscriber;
use App\Service\ActivityLogService;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Controller used to manage newsletter subscribers in the backend.
 */
#[Route('/admin/newsletter')]
#[IsGranted('ROLE_ADMIN')]
class NewsletterController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ActivityLogService $activityLogService,
    ) {
    }

    /**
     * Lists all NewsletterSubscriber entities.
     */
    #[Route('/', name: 'admin_newsletter_index', methods: ['GET'])]
    public function indexAction(Request $request, PaginatorInterface $paginator)
    {
        $repository = $this->em->getRepository(NewsletterSubscriber::class);
        $filters = $this->filters($request);
        $qb = $repository->createQueryBuilder('s');
        if ($filters['q'] !== '') {
            $qb->andWhere('s.email LIKE :q OR s.ip LIKE :q')->setParameter('q', '%' . $filters['q'] . '%');
        }
        if ($filters['status'] !== '') {
            $qb->andWhere('s.isRead = :read')->setParameter('read', $filters['status'] === 'read');
        }
        $qb->orderBy('s.createdAt', 'DESC')->addOrderBy('s.id', 'DESC');

        return $this->render('admin/newsletter/index.html.twig', [
            'pagination' => $paginator->paginate($qb, max(1, $request->query->getInt('page', 1)), 20),
            'filters' => $filters,
            'counts' => ['all' => $repository->count([]), 'read' => $repository->count(['isRead' => true]), 'unread' => $repository->count(['isRead' => false])],
        ]);
    }

    #[Route('/{id}/read', requirements: ['id' => '\d+'], name: 'admin_newsletter_read', methods: ['POST'])]
    public function readAction(Request $request, NewsletterSubscriber $subscriber)
    {
        if (!$this->isCsrfTokenValid('newsletter_read_' . $subscriber->getId(), $request->request->get('token'))) {
            $this->addFlash('warning', 'Phiên thao tác không hợp lệ. Vui lòng thử lại.');
        } elseif (in_array($request->request->get('is_read'), ['0', '1'], true)) {
            $subscriber->setIsRead($request->request->get('is_read') === '1');
            $this->em->flush();
            $this->activityLogService->log(ActivityLog::ACTION_TOGGLE, ActivityLog::ENTITY_NEWSLETTER, $subscriber->getId(), $subscriber->getEmail(), $subscriber->getIsRead() ? 'Đã đọc' : 'Chưa đọc');
            $this->addFlash('success', $subscriber->getIsRead() ? 'Đã đánh dấu đăng ký là đã đọc.' : 'Đã đánh dấu đăng ký là chưa đọc.');
        }
        return $this->redirectToRoute('admin_newsletter_index', $this->returnQuery($request));
    }

    private function filters(Request $request): array
    {
        $status = (string) $request->query->get('status', '');
        return ['q' => trim((string) $request->query->get('q', '')), 'status' => in_array($status, ['read', 'unread'], true) ? $status : ''];
    }

    private function returnQuery(Request $request): array
    {
        return $this->filters($request) + ['page' => max(1, $request->query->getInt('page', 1))];
    }

    /**
     * Deletes a NewsletterSubscriber entity.
     */
    #[Route('/{id}/delete', name: 'admin_newsletter_delete', methods: ['POST'])]
    public function deleteAction(Request $request, NewsletterSubscriber $subscriber)
    {
        if (!$this->isCsrfTokenValid('delete', $request->request->get('token'))) {
            $this->addFlash('warning', 'Phiên thao tác không hợp lệ. Vui lòng thử lại.');
            return $this->redirectToRoute('admin_newsletter_index', $this->returnQuery($request));
        }

        $email = $subscriber->getEmail();
        $id = $subscriber->getId();

        $this->em->remove($subscriber);
        $this->em->flush();

        // Activity Log
        $this->activityLogService->log(
            ActivityLog::ACTION_DELETE,
            ActivityLog::ENTITY_NEWSLETTER,
            $id,
            $email
        );

        $this->addFlash('success', 'action.deleted_successfully');

        return $this->redirectToRoute('admin_newsletter_index', $this->returnQuery($request));
    }
}
