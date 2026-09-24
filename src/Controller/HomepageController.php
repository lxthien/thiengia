<?php

namespace App\Controller;

use App\Service\Homepage\HomepageDataProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

class HomepageController extends AbstractController
{
    public function __construct(private readonly HomepageDataProvider $dataProvider)
    {
    }

    public function indexAction(): Response
    {
        return $this->render('homepage/index.html.twig', [
            'sections' => $this->dataProvider->getSections(),
        ]);
    }
}
