<?php

namespace App\Service\Homepage;

use App\Entity\HomepageSection;
use App\Enum\SectionType;
use App\Repository\GalleryAlbumRepository;

final class GallerySectionResolver implements SectionResolverInterface
{
    public function __construct(private readonly GalleryAlbumRepository $repository)
    {
    }

    public function supports(SectionType $type): bool
    {
        return $type === SectionType::Gallery;
    }

    public function resolve(HomepageSection $section): array
    {
        return ['galleryAlbums' => $this->repository->findActiveOrdered()];
    }
}
