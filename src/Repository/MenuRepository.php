<?php

namespace App\Repository;

use Doctrine\ORM\EntityRepository;

class MenuRepository extends EntityRepository
{
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
