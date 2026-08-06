<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', get_class($user)));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

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
