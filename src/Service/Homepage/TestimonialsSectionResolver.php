<?php

namespace App\Service\Homepage;

use App\Entity\HomepageSection;
use App\Enum\SectionType;
use App\Repository\TestimonialRepository;

final class TestimonialsSectionResolver implements SectionResolverInterface
{
    public function __construct(private readonly TestimonialRepository $repository)
    {
    }

    public function supports(SectionType $type): bool
    {
        return $type === SectionType::Testimonials;
    }

    public function resolve(HomepageSection $section): array
    {
        return ['testimonials' => $this->repository->findActiveOrdered()];
    }
}
