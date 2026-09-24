<?php

namespace App\Repository;

use App\Entity\Project;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Project>
 */
class ProjectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Project::class);
    }

    /** @return Project[] */
    public function findAllOrdered(): array
    {
        return $this->findBy([], ['position' => 'ASC', 'id' => 'ASC']);
    }

    /** @return Project[] */
    public function findActiveOrdered(): array
    {
        return $this->findBy(['enable' => true], ['position' => 'ASC', 'id' => 'ASC']);
    }

    /**
     * Công trình hiện trên trang chủ: đang bật VÀ được đánh dấu nổi bật.
     *
     * @return Project[]
     */
    public function findFeatured(?int $limit = null): array
    {
        $builder = $this->createQueryBuilder('p')
            ->where('p.enable = :enable')->andWhere('p.featuredOnHome = :featured')
            ->setParameter('enable', true)->setParameter('featured', true)
            ->orderBy('p.position', 'ASC')->addOrderBy('p.id', 'ASC');

        if ($limit !== null && $limit > 0) {
            $builder->setMaxResults($limit);
        }

        return $builder->getQuery()->getResult();
    }

    /** @return Project[] */
    public function findForReorder(): array
    {
        return $this->createQueryBuilder('p')
            ->orderBy('p.position', 'ASC')->addOrderBy('p.id', 'ASC')
            ->getQuery()->setLockMode(\Doctrine\DBAL\LockMode::PESSIMISTIC_WRITE)->getResult();
    }
}
