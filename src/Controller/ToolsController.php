<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ToolsController extends AbstractController
{
    #[Route('cong-cu/thuoc-lo-ban/', name: 'lo_ban_calculator')]
    public function loBanCalculatorAction(): Response
    {
        return $this->render('tools/lo_ban.html.twig');
    }
}
