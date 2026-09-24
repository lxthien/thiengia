<?php

namespace App\Repository;

use App\Entity\Service;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Service>
 */
class ServiceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Service::class);
    }

    /** @return Service[] */
    public function findAllOrdered(): array
    {
        return $this->findBy([], ['position' => 'ASC', 'id' => 'ASC']);
    }

    /** @return Service[] */
    public function findActiveOrdered(): array
    {
        return $this->findBy(['enable' => true], ['position' => 'ASC', 'id' => 'ASC']);
    }

    /**
     * Dịch vụ có thẻ trên trang chủ: đang bật VÀ được đánh dấu nổi bật.
     *
     * @return Service[]
     */
    public function findFeatured(?int $limit = null): array
    {
        $builder = $this->createQueryBuilder('s')
            ->where('s.enable = :enable')->andWhere('s.featuredOnHome = :featured')
            ->setParameter('enable', true)->setParameter('featured', true)
            ->orderBy('s.position', 'ASC')->addOrderBy('s.id', 'ASC');

        if ($limit !== null && $limit > 0) {
            $builder->setMaxResults($limit);
        }

        return $builder->getQuery()->getResult();
    }

    /** @return Service[] */
    public function findForReorder(): array
    {
        return $this->createQueryBuilder('s')
            ->orderBy('s.position', 'ASC')->addOrderBy('s.id', 'ASC')
            ->getQuery()->setLockMode(\Doctrine\DBAL\LockMode::PESSIMISTIC_WRITE)->getResult();
    }

    /**
     * Tên các dịch vụ đang bật — dùng cho danh sách nhu cầu ở form báo giá.
     *
     * @return list<string>
     */
    public function findActiveNames(): array
    {
        $rows = $this->createQueryBuilder('s')
            ->select('s.name')
            ->where('s.enable = :enable')->setParameter('enable', true)
            ->orderBy('s.position', 'ASC')->addOrderBy('s.id', 'ASC')
            ->getQuery()->getScalarResult();

        return array_values(array_filter(array_column($rows, 'name')));
    }
}
