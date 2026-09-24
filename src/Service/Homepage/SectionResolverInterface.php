<?php

namespace App\Service\Homepage;

use App\Entity\HomepageSection;
use App\Enum\SectionType;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Lấy dữ liệu động cho một khối trang chủ.
 *
 * Khối nào chỉ hiển thị nội dung trong cấu hình theme thì không cần resolver.
 */
#[AutoconfigureTag('app.homepage_section_resolver')]
interface SectionResolverInterface
{
    public function supports(SectionType $type): bool;

    /**
     * Các biến bổ sung truyền cho template của khối.
     *
     * @return array<string, mixed>
     */
    public function resolve(HomepageSection $section): array;
}
