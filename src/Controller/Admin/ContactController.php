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
    public function indexAction()
    {
        $contactRepository = $this->em->getRepository(Contact::class);
        $contactRepository->markAllAsRead();

        $contacts = $contactRepository->findBy(
            array(),
            array('createdAt' => 'DESC')
        );

        return $this->render('admin/contact/index.html.twig', [
            'objects' => $contacts
        ]);
    }

    /**
     * Deletes a Contact entity.
     */
    #[Route('/{id}/delete', name: 'admin_contact_delete', methods: ['POST'])]
    public function deleteAction(Request $request, Contact $contact)
    {
        if (!$this->isCsrfTokenValid('delete', $request->request->get('token'))) {
            return $this->redirectToRoute('admin_contact_index');
        }

        $this->em->remove($contact);
        $this->em->flush();

        $this->addFlash('success', 'action.deleted_successfully');

        return $this->redirectToRoute('admin_contact_index');
    }
}
