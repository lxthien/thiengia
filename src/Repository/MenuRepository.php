<?php

namespace App\Repository;

use App\Entity\Menu;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class MenuRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Menu::class);
    }

    /**
     * Find all menus with their items
     *
     * @return array
     */
    public function findAllWithItems()
    {
        return $this->createQueryBuilder('m')
            ->leftJoin('m.items', 'mi')
            ->addSelect('mi')
            ->orderBy('m.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find enabled menus only
     *
     * @return array
     */
    public function findEnabled()
    {
        return $this->createQueryBuilder('m')
            ->where('m.enable = :enable')
            ->setParameter('enable', true)
            ->orderBy('m.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
