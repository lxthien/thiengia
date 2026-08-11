<?php

namespace App\Controller\Admin;

use App\Entity\ActivityLog;
use App\Entity\NewsletterSubscriber;
use App\Service\ActivityLogService;
use Doctrine\ORM\EntityManagerInterface;
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
    public function indexAction()
    {
        $repository = $this->em->getRepository(NewsletterSubscriber::class);
        $repository->markAllAsRead();

        $subscribers = $repository->findBy(
            array(),
            array('createdAt' => 'DESC')
        );

        return $this->render('admin/newsletter/index.html.twig', [
            'objects' => $subscribers,
        ]);
    }

    /**
     * Deletes a NewsletterSubscriber entity.
     */
    #[Route('/{id}/delete', name: 'admin_newsletter_delete', methods: ['POST'])]
    public function deleteAction(Request $request, NewsletterSubscriber $subscriber)
    {
        if (!$this->isCsrfTokenValid('delete', $request->request->get('token'))) {
            return $this->redirectToRoute('admin_newsletter_index');
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

        return $this->redirectToRoute('admin_newsletter_index');
    }
}
