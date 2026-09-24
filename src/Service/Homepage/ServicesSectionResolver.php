<?php

namespace App\Service\Homepage;

use App\Entity\HomepageSection;
use App\Enum\SectionType;
use App\Repository\ServiceRepository;

/**
 * Khối "Dịch vụ": đọc entity Service đang bật và được đánh dấu đưa lên
 * trang chủ (admin: Dịch vụ).
 */
final class ServicesSectionResolver implements SectionResolverInterface
{
    public function __construct(private readonly ServiceRepository $repository)
    {
    }

    public function supports(SectionType $type): bool
    {
        return $type === SectionType::Services;
    }

    public function resolve(HomepageSection $section): array
    {
        $limit = (int) $section->getConfigValue('limit', 0);

        return ['services' => $this->repository->findFeatured($limit > 0 ? $limit : null)];
    }
}
