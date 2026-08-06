<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Repository;

use Doctrine\ORM\EntityRepository;

/**
 * This custom Doctrine repository is empty because so far we don't need any custom
 * method to query for application user information. But it's always a good practice
 * to define a custom repository that will be used when the application grows.
 *
 * See https://symfony.com/doc/current/book/doctrine.html#custom-repository-classes
 *
 * @author Ryan Weaver <weaverryan@gmail.com>
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 */
class ContactRepository extends EntityRepository
{
    public function countUnread()
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.isRead = :isRead')
            ->setParameter('isRead', false)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findUnreadNotifications($limit = 5)
    {
        return $this->createQueryBuilder('c')
            ->where('c.isRead = :isRead')
            ->setParameter('isRead', false)
            ->orderBy('c.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function markAllAsRead()
    {
        return $this->createQueryBuilder('c')
            ->update()
            ->set('c.isRead', ':isRead')
            ->where('c.isRead = :currentIsRead')
            ->setParameter('isRead', true)
            ->setParameter('currentIsRead', false)
            ->getQuery()
            ->execute();
    }
}
