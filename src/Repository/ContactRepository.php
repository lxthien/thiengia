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

use App\Entity\Contact;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ContactRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Contact::class);
    }

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
