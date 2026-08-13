<?php

namespace App\Repository;

use App\Entity\MediaAsset;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method MediaAsset|null find($id, $lockMode = null, $lockVersion = null)
 * @method MediaAsset|null findOneBy(array $criteria, array $orderBy = null)
 * @method MediaAsset[]    findAll()
 * @method MediaAsset[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class MediaAssetRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MediaAsset::class);
    }

    /**
     * Map đường dẫn (path) => alt text, cho 1 tập path — dùng để tránh N+1
     * khi render danh sách media hoặc post_card.
     *
     * @param string[] $paths
     * @return array<string, string>
     */
    public function findAltTextMap(array $paths): array
    {
        if (empty($paths)) {
            return [];
        }

        $rows = $this->createQueryBuilder('m')
            ->select('m.path', 'm.altText')
            ->where('m.path IN (:paths)')
            ->andWhere('m.altText IS NOT NULL')
            ->setParameter('paths', $paths)
            ->getQuery()
            ->getArrayResult();

        $map = [];
        foreach ($rows as $row) {
            $map[$row['path']] = $row['altText'];
        }

        return $map;
    }
}
