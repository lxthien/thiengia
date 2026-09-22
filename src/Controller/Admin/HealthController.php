<?php

namespace App\Controller\Admin;

use App\Repository\HealthReportRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/health')]
#[IsGranted('ROLE_ADMIN')]
class HealthController extends AbstractController
{
    /**
     * Đọc report đã tính sẵn bởi app:compute-health-report (cron) — không
     * tự audit lại (DOM parse toàn bộ nội dung, quét thư mục upload) lúc
     * request.
     */
    #[Route('/', name: 'admin_health_index', methods: ['GET'])]
    public function indexAction(HealthReportRepository $healthReportRepository, \App\Health\HealthAuditManager $healthAudit)
    {
        $latest = $healthReportRepository->findLatest();

        if (!$latest) {
            return $this->render('admin/health/index.html.twig', [
                'report' => null,
            ]);
        }

        $data = $latest->getData();
        $data['generatedAt'] = $latest->getGeneratedAt();
        // Old snapshots may contain external URLs misclassified by the CLI audit.
        $data['brokenLinks'] = array_values(array_filter($data['brokenLinks'] ?? [], static fn (array $item): bool => $healthAudit->normalizeLocalUrl($item['url'] ?? $item['path'] ?? '') !== null));
        $data['summary']['brokenLinks'] = count($data['brokenLinks']);

        return $this->render('admin/health/index.html.twig', [
            'report' => $data,
        ]);
    }
}
