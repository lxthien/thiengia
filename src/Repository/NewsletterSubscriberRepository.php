<?php

namespace App\Repository;

use App\Entity\NewsletterSubscriber;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class NewsletterSubscriberRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NewsletterSubscriber::class);
    }

    public function findOneByEmail(string $email): ?NewsletterSubscriber
    {
        return $this->findOneBy(['email' => $email]);
    }

    public function countUnread()
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->where('s.isRead = :isRead')
            ->setParameter('isRead', false)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findUnreadNotifications($limit = 5)
    {
        return $this->createQueryBuilder('s')
            ->where('s.isRead = :isRead')
            ->setParameter('isRead', false)
            ->orderBy('s.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function markAllAsRead()
    {
        return $this->createQueryBuilder('s')
            ->update()
            ->set('s.isRead', ':isRead')
            ->where('s.isRead = :currentIsRead')
            ->setParameter('isRead', true)
            ->setParameter('currentIsRead', false)
            ->getQuery()
            ->execute();
    }
}
