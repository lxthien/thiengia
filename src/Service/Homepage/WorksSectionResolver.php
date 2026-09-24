<?php

namespace App\Service\Homepage;

use App\Entity\HomepageSection;
use App\Enum\SectionType;
use App\Repository\ProjectRepository;

/**
 * Khối "Công trình thực tế": đọc entity Project đang bật và được đánh dấu
 * đưa lên trang chủ (admin: Công trình).
 */
final class WorksSectionResolver implements SectionResolverInterface
{
    public function __construct(private readonly ProjectRepository $repository)
    {
    }

    public function supports(SectionType $type): bool
    {
        return $type === SectionType::Works;
    }

    public function resolve(HomepageSection $section): array
    {
        $limit = (int) $section->getConfigValue('limit', 0);

        return ['projects' => $this->repository->findFeatured($limit > 0 ? $limit : null)];
    }
}
