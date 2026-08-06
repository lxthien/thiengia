<?php

namespace App\Controller\Admin;

use App\Health\HealthAuditManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/health')]
#[IsGranted('ROLE_ADMIN')]
class HealthController extends AbstractController
{
    #[Route('/', name: 'admin_health_index', methods: ['GET'])]
    public function indexAction(HealthAuditManager $healthAudit)
    {
        return $this->render('admin/health/index.html.twig', [
            'report' => $healthAudit->buildReport(),
        ]);
    }
}
