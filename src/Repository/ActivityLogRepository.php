<?php

namespace App\Repository;

use Doctrine\ORM\EntityRepository;

/**
 * ActivityLogRepository
 */
class ActivityLogRepository extends EntityRepository
{
    /**
     * Find recent activity logs
     *
     * @param int $limit
     * @return array
     */
    public function findRecentLogs($limit = 50)
    {
        return $this->createQueryBuilder('a')
            ->leftJoin('a.user', 'u')
            ->addSelect('u')
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find activity logs with filters and pagination
     *
     * @param array $filters
     * @param int $page
     * @param int $perPage
     * @return array ['items' => [], 'total' => int, 'pages' => int]
     */
    public function findByFilters(array $filters = [], $page = 1, $perPage = 30)
    {
        $qb = $this->createQueryBuilder('a')
            ->leftJoin('a.user', 'u')
            ->addSelect('u');

        // Filter by user
        if (!empty($filters['userId'])) {
            $qb->andWhere('a.user = :userId')
                ->setParameter('userId', $filters['userId']);
        }

        // Filter by action
        if (!empty($filters['action'])) {
            $qb->andWhere('a.action = :action')
                ->setParameter('action', $filters['action']);
        }

        // Filter by entity type
        if (!empty($filters['entityType'])) {
            $qb->andWhere('a.entityType = :entityType')
                ->setParameter('entityType', $filters['entityType']);
        }

        // Filter by date range
        if (!empty($filters['dateFrom'])) {
            $dateFrom = new \DateTime($filters['dateFrom']);
            $dateFrom->setTime(0, 0, 0);
            $qb->andWhere('a.createdAt >= :dateFrom')
                ->setParameter('dateFrom', $dateFrom);
        }

        if (!empty($filters['dateTo'])) {
            $dateTo = new \DateTime($filters['dateTo']);
            $dateTo->setTime(23, 59, 59);
            $qb->andWhere('a.createdAt <= :dateTo')
                ->setParameter('dateTo', $dateTo);
        }

        // Search by entity title
        if (!empty($filters['search'])) {
            $qb->andWhere('a.entityTitle LIKE :search OR a.details LIKE :search')
                ->setParameter('search', '%' . $filters['search'] . '%');
        }

        // Count total
        $countQb = clone $qb;
        $total = $countQb->select('COUNT(a.id)')
            ->getQuery()
            ->getSingleScalarResult();

        // Paginate
        $qb->orderBy('a.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        $items = $qb->getQuery()->getResult();

        return [
            'items' => $items,
            'total' => (int) $total,
            'pages' => (int) ceil($total / $perPage),
            'currentPage' => $page,
        ];
    }
}
