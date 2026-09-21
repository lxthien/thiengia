<?php

namespace App\Repository;

use App\Entity\Banner;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\Persistence\ManagerRegistry;

class BannerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Banner::class);
    }

    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('b')->leftJoin('b.bannercategory', 'c')->addSelect('c')
            ->orderBy('b.position', 'ASC')->addOrderBy('b.id', 'ASC')->getQuery()->getResult();
    }

    public function findForZone(string $zone, bool $lock = false): array
    {
        $query = $this->createQueryBuilder('b')->join('b.bannercategory', 'c')->addSelect('c')
            ->where('c.zone = :zone')->setParameter('zone', $zone)
            ->orderBy('b.position', 'ASC')->addOrderBy('b.id', 'ASC')->getQuery();
        if ($lock) {
            $query->setLockMode(LockMode::PESSIMISTIC_WRITE);
        }
        return $query->getResult();
    }

    public function findActiveByZone(string $zone): array
    {
        return $this->createQueryBuilder('b')->join('b.bannercategory', 'c')
            ->andWhere('c.zone = :zone')->andWhere('b.enable = :enable')
            ->setParameter('zone', $zone)->setParameter('enable', true)
            ->orderBy('b.position', 'ASC')->addOrderBy('b.id', 'ASC')->getQuery()->getResult();
    }
}
