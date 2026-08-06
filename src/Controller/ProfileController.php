<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use App\Service\SettingsManager;

use App\Entity\User;
use App\Entity\News;

class ProfileController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PaginatorInterface $paginator,
        private readonly SettingsManager $settingsManager,
    ) {
    }

    #[Route(
        '/author/{slug}/{page}',
        name: 'author',
        requirements: ['slug' => '[-\w]+', 'page' => '\d+']
    )]
    public function indexAction($slug, $page = 1)
    {
        $user = $this->em
            ->getRepository(User::class)
            ->findOneBy(
                array('username' => $slug)
            );

        if (!$user) {
            return $this->redirectToRoute('homepage', [], 302);
        }

        $posts = $this->em
            ->getRepository(News::class)
            ->createQueryBuilder('n')
            ->where('n.author = :author')
            ->andWhere('n.enable = :enable')
            ->andWhere('n.postType = :postType')
            ->setParameter('author', $user->getId())
            ->setParameter('enable', 1)
            ->setParameter('postType', 'post')
            ->orderBy('n.createdAt', 'DESC')
            ->getQuery()->getResult();

        $pagination = $this->paginator->paginate(
            $posts,
            $page,
            $this->settingsManager->get('numberRecordOnPage') ?: 10
        );

        return $this->render('user/list.html.twig', [
            'baseUrl' => $this->generateUrl('author', array('slug' => $slug), UrlGeneratorInterface::ABSOLUTE_URL),
            'user' => $user,
            'pagination' => $pagination
        ]);
    }
}
