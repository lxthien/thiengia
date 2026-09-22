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

use App\Entity\Contact;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Controller used to manage blog contents in the backend.
 */
#[Route('/admin/contact')]
#[IsGranted('ROLE_ADMIN')]
class ContactController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Lists all Contact entities.
     */
    #[Route('/', name: 'admin_contact_index', methods: ['GET'])]
    public function indexAction(Request $request, PaginatorInterface $paginator)
    {
        $contactRepository = $this->em->getRepository(Contact::class);
        $filters = $this->filters($request);
        $qb = $contactRepository->createQueryBuilder('c');
        if ($filters['q'] !== '') {
            $qb->andWhere('c.name LIKE :q OR c.email LIKE :q OR c.phone LIKE :q OR c.title LIKE :q OR c.contents LIKE :q')
                ->setParameter('q', '%' . $filters['q'] . '%');
        }
        if ($filters['status'] !== '') {
            $qb->andWhere('c.isRead = :read')->setParameter('read', $filters['status'] === 'read');
        }
        $qb->orderBy('c.createdAt', 'DESC')->addOrderBy('c.id', 'DESC');
        $pagination = $paginator->paginate($qb, max(1, $request->query->getInt('page', 1)), 20);

        return $this->render('admin/contact/index.html.twig', [
            'pagination' => $pagination,
            'filters' => $filters,
            'counts' => [
                'all' => $contactRepository->count([]),
                'unread' => $contactRepository->count(['isRead' => false]),
                'read' => $contactRepository->count(['isRead' => true]),
            ],
        ]);
    }

    #[Route('/{id}/read', requirements: ['id' => '\d+'], name: 'admin_contact_read', methods: ['POST'])]
    public function readAction(Request $request, Contact $contact)
    {
        if (!$this->isCsrfTokenValid('contact_read_' . $contact->getId(), $request->request->get('token'))) {
            $this->addFlash('warning', 'Phiên thao tác không hợp lệ. Vui lòng thử lại.');
        } elseif (in_array($request->request->get('is_read'), ['0', '1'], true)) {
            $contact->setIsRead($request->request->get('is_read') === '1');
            $this->em->flush();
            $this->addFlash('success', $contact->getIsRead() ? 'Đã đánh dấu liên hệ là đã đọc.' : 'Đã đánh dấu liên hệ là chưa đọc.');
        }
        return $this->redirectToRoute('admin_contact_index', $this->returnQuery($request));
    }

    private function filters(Request $request): array
    {
        $status = (string) $request->query->get('status', '');
        return [
            'q' => trim((string) $request->query->get('q', '')),
            'status' => in_array($status, ['read', 'unread'], true) ? $status : '',
        ];
    }

    private function returnQuery(Request $request): array
    {
        return $this->filters($request) + ['page' => max(1, $request->query->getInt('page', 1))];
    }

    /**
     * Deletes a Contact entity.
     */
    #[Route('/{id}/delete', name: 'admin_contact_delete', methods: ['POST'])]
    public function deleteAction(Request $request, Contact $contact)
    {
        if (!$this->isCsrfTokenValid('delete', $request->request->get('token'))) {
            $this->addFlash('warning', 'Phiên thao tác không hợp lệ. Vui lòng thử lại.');
            return $this->redirectToRoute('admin_contact_index', $this->returnQuery($request));
        }

        $this->em->remove($contact);
        $this->em->flush();

        $this->addFlash('success', 'action.deleted_successfully');

        return $this->redirectToRoute('admin_contact_index', $this->returnQuery($request));
    }
}
