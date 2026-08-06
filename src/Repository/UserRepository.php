<?php

namespace App\Repository;

use Doctrine\ORM\EntityRepository;

class UserRepository extends EntityRepository
{
    public function countUnreadRegistrationNotifications()
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.adminNotificationRead = :adminNotificationRead')
            ->setParameter('adminNotificationRead', false)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findUnreadRegistrationNotifications($limit = 5)
    {
        return $this->createQueryBuilder('u')
            ->where('u.adminNotificationRead = :adminNotificationRead')
            ->setParameter('adminNotificationRead', false)
            ->orderBy('u.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function markAllRegistrationNotificationsAsRead()
    {
        return $this->createQueryBuilder('u')
            ->update()
            ->set('u.adminNotificationRead', ':adminNotificationRead')
            ->where('u.adminNotificationRead = :currentNotificationState')
            ->setParameter('adminNotificationRead', true)
            ->setParameter('currentNotificationState', false)
            ->getQuery()
            ->execute();
    }
}
