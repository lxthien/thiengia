<?php

namespace App\Repository;

use App\Entity\InternalLinkReport;
use App\Seo\InternalLink\InternalLinkAnalyzer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class InternalLinkReportRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InternalLinkReport::class);
    }

    public function findLatest(): ?InternalLinkReport
    {
        return $this->createQueryBuilder('r')
            ->orderBy('r.generatedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Chạy lại phân tích và thay bản báo cáo cũ bằng bản mới.
     */
    public function refresh(InternalLinkAnalyzer $analyzer): InternalLinkReport
    {
        $report = (new InternalLinkReport())->setData($analyzer->analyze());
        $em = $this->getEntityManager();

        $em->createQueryBuilder()->delete(InternalLinkReport::class, 'r')->getQuery()->execute();
        $em->persist($report);
        $em->flush();

        return $report;
    }
}
