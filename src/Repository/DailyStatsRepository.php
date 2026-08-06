<?php

namespace App\Repository;

use App\Entity\DailyStats;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * DailyStatsRepository
 */
class DailyStatsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DailyStats::class);
    }

    /**
     * Get trends for the last X days
     * 
     * @param int $days
     * @return array [date => count]
     */
    public function getTrends($days = 30)
    {
        $startDate = new \DateTime("-$days days");
        $startDate->setTime(0, 0, 0);

        $results = $this->createQueryBuilder('s')
            ->select('s.date, s.viewCount')
            ->where('s.date >= :startDate')
            ->setParameter('startDate', $startDate)
            ->orderBy('s.date', 'ASC')
            ->getQuery()
            ->getResult();

        $trends = [];
        
        // Fill gaps with 0
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = new \DateTime("-$i days");
            $dateStr = $date->format('Y-m-d');
            $trends[$dateStr] = 0;
        }

        foreach ($results as $result) {
            $dateStr = $result['date']->format('Y-m-d');
            $trends[$dateStr] = (int) $result['viewCount'];
        }

        return $trends;
    }

    /**
     * Increment view count for a specific date
     */
    public function incrementViews(\DateTime $date, $amount = 1)
    {
        $dateStr = $date->format('Y-m-d');
        
        $qb = $this->_em->createQueryBuilder();
        $qb->update($this->_entityName, 's')
            ->set('s.viewCount', 's.viewCount + :amount')
            ->where('s.date = :date')
            ->setParameter('amount', $amount)
            ->setParameter('date', $dateStr)
            ->getQuery()
            ->execute();
    }
}
