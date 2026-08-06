<?php

namespace App\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

use App\Entity\User;
use App\Entity\News;

class ProfileController extends Controller
{
    /**
     * @Route("/author/{slug}/{page}",
     *      name="author",
     *      requirements={
     *          "slug": "[-\w]+",
     *          "page": "\d+"
     *      }))
     */
    public function indexAction($slug, $page = 1)
    {
        $user = $this->getDoctrine()
            ->getRepository(User::class)
            ->findOneBy(
                array('username' => $slug)
            );

        if (!$user) {
            return $this->redirectToRoute('homepage', [], 302);
        }

        $posts = $this->getDoctrine()
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

        $paginator  = $this->get('knp_paginator');
        $pagination = $paginator->paginate(
            $posts,
            $page,
            $this->get('settings_manager')->get('numberRecordOnPage') ?: 10
        );

        return $this->render('user/list.html.twig', [
            'baseUrl' => $this->generateUrl('author', array('slug' => $slug), UrlGeneratorInterface::ABSOLUTE_URL),
            'user' => $user,
            'pagination' => $pagination
        ]);
    }
}
