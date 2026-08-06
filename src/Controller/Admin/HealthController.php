<?php

namespace App\Controller\Admin;

use App\Health\HealthAuditManager;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

/**
 * @Route("/admin/health")
 * @Security("has_role('ROLE_ADMIN')")
 */
class HealthController extends Controller
{
    /**
     * @Route("/", name="admin_health_index")
     * @Method("GET")
     */
    public function indexAction(HealthAuditManager $healthAudit)
    {
        return $this->render('admin/health/index.html.twig', [
            'report' => $healthAudit->buildReport(),
        ]);
    }
}
