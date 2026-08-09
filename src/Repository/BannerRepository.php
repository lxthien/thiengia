<?php

namespace App\Repository;

use App\Entity\Banner;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class BannerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Banner::class);
    }

    /**
     * @return Banner[]
     */
    public function findAllOrdered(): array
    {
        return $this->findBy([], ['position' => 'ASC']);
    }

    /**
     * Active banners belonging to categories of the given display zone.
     *
     * @return Banner[]
     */
    public function findActiveByZone(string $zone): array
    {
        return $this->createQueryBuilder('b')
            ->join('b.bannercategory', 'c')
            ->andWhere('c.zone = :zone')
            ->andWhere('b.enable = :enable')
            ->setParameter('zone', $zone)
            ->setParameter('enable', true)
            ->orderBy('b.position', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
