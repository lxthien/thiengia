<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ToolsController extends AbstractController
{
    #[Route('/cong-cu/xem-huong-nha/', name: 'house_direction_calculator', methods: ['GET'])]
    public function houseDirectionAction(): Response
    {
        return $this->render('tools/house_direction.html.twig');
    }

    #[Route('/cong-cu/xem-tuoi-xay-nha/', name: 'building_age_calculator', methods: ['GET'])]
    public function buildingAgeAction(): Response
    {
        // A suggested input, not a Gregorian-to-lunar conversion.
        $year = (int) (new \DateTimeImmutable('now', new \DateTimeZone('Asia/Ho_Chi_Minh')))->format('Y');
        return $this->render('tools/building_age.html.twig', [
            'suggested_year' => max(1900, min(2100, $year)),
        ]);
    }

    #[Route('/cong-cu/mat-do-xay-dung/', name: 'building_density_calculator', methods: ['GET'])]
    public function buildingDensityAction(): Response
    {
        $today = new \DateTimeImmutable('now', new \DateTimeZone('Asia/Ho_Chi_Minh'));
        return $this->render('tools/building_density.html.twig', [
            'needs_review' => $today->format('Y-m-d') >= '2027-01-01',
        ]);
    }

    #[Route('cong-cu/thuoc-lo-ban/', name: 'lo_ban_calculator')]
    public function loBanCalculatorAction(): Response
    {
        return $this->render('tools/lo_ban.html.twig');
    }
}
