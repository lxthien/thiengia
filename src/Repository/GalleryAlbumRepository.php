<?php

namespace App\Repository;

use App\Entity\GalleryAlbum;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class GalleryAlbumRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GalleryAlbum::class);
    }

    /**
     * @return GalleryAlbum[]
     */
    public function findAllOrdered(): array
    {
        return $this->findBy([], ['position' => 'ASC']);
    }

    /**
     * Active albums with images eagerly loaded (avoids N+1 for cover images).
     *
     * @return GalleryAlbum[]
     */
    public function findActiveOrdered(): array
    {
        return $this->createQueryBuilder('a')
            ->addSelect('i')
            ->leftJoin('a.images', 'i')
            ->andWhere('a.enable = :enable')
            ->setParameter('enable', true)
            ->orderBy('a.position', 'ASC')
            ->addOrderBy('i.position', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
