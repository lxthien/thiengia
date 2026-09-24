<?php

namespace App\Repository;

use App\Entity\HomepageSection;
use App\Enum\SectionType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<HomepageSection>
 */
class HomepageSectionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HomepageSection::class);
    }

    /** @return HomepageSection[] */
    public function findAllOrdered(): array
    {
        return $this->findBy([], ['position' => 'ASC', 'id' => 'ASC']);
    }

    /** @return HomepageSection[] */
    public function findActiveOrdered(): array
    {
        return $this->findBy(['enable' => true], ['position' => 'ASC', 'id' => 'ASC']);
    }

    /** @return HomepageSection[] */
    public function findForReorder(): array
    {
        return $this->createQueryBuilder('s')
            ->orderBy('s.position', 'ASC')->addOrderBy('s.id', 'ASC')
            ->getQuery()->setLockMode(\Doctrine\DBAL\LockMode::PESSIMISTIC_WRITE)->getResult();
    }

    public function findOneByType(SectionType $type): ?HomepageSection
    {
        return $this->findOneBy(['type' => $type]);
    }
}
