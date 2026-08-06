<?php

namespace App\Repository;

use Doctrine\ORM\EntityRepository;
use App\Entity\Menu;

class MenuItemRepository extends EntityRepository
{
    /**
     * Find all menu items for a specific menu with hierarchy
     *
     * @param Menu $menu
     * @return array
     */
    public function findByMenuOrdered(Menu $menu)
    {
        return $this->createQueryBuilder('mi')
            ->where('mi.menu = :menu')
            ->andWhere('mi.parent IS NULL')
            ->setParameter('menu', $menu)
            ->addOrderBy('mi.position', 'ASC')
            ->addOrderBy('mi.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find children for a parent menu item
     *
     * @param MenuItem $parent
     * @return array
     */
    public function findChildren($parent)
    {
        return $this->createQueryBuilder('mi')
            ->where('mi.parent = :parent')
            ->setParameter('parent', $parent)
            ->addOrderBy('mi.position', 'ASC')
            ->addOrderBy('mi.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find enabled menu items for a menu
     *
     * @param Menu $menu
     * @return array
     */
    public function findEnabledByMenu(Menu $menu)
    {
        return $this->createQueryBuilder('mi')
            ->where('mi.menu = :menu')
            ->andWhere('mi.enable = :enable')
            ->andWhere('mi.parent IS NULL')
            ->setParameter('menu', $menu)
            ->setParameter('enable', true)
            ->addOrderBy('mi.position', 'ASC')
            ->addOrderBy('mi.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Check whether a target content item already exists in the given menu.
     *
     * @param Menu $menu
     * @param string $targetType
     * @param int $targetId
     * @return bool
     */
    public function existsByMenuAndTarget(Menu $menu, $targetType, $targetId)
    {
        $count = $this->createQueryBuilder('mi')
            ->select('COUNT(mi.id)')
            ->where('mi.menu = :menu')
            ->andWhere('mi.targetType = :targetType')
            ->andWhere('mi.targetId = :targetId')
            ->setParameter('menu', $menu)
            ->setParameter('targetType', $targetType)
            ->setParameter('targetId', $targetId)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count > 0;
    }
}
