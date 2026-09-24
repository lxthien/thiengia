<?php

namespace App\Service\Homepage;

use App\Entity\HomepageSection;
use App\Repository\HomepageSectionRepository;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Dựng danh sách khối cho trang chủ: đọc thứ tự/bật-tắt từ DB rồi nhờ
 * resolver tương ứng lấy dữ liệu động của từng khối.
 *
 * Nhờ lớp này HomepageController không phải biết khối nào cần query gì.
 */
final class HomepageDataProvider
{
    /**
     * @param iterable<SectionResolverInterface> $resolvers
     */
    public function __construct(
        private readonly HomepageSectionRepository $repository,
        #[AutowireIterator('app.homepage_section_resolver')]
        private readonly iterable $resolvers,
    ) {
    }

    /**
     * @param bool $includeHidden true khi editor xem trước (?preview=1)
     *
     * @return list<array{section: HomepageSection, data: array<string, mixed>}>
     */
    public function getSections(bool $includeHidden = false): array
    {
        $sections = $includeHidden
            ? $this->repository->findAllOrdered()
            : $this->repository->findActiveOrdered();

        $result = [];

        foreach ($sections as $section) {
            $result[] = [
                'section' => $section,
                'data' => $this->resolveData($section),
            ];
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveData(HomepageSection $section): array
    {
        $data = [];

        foreach ($this->resolvers as $resolver) {
            if ($resolver->supports($section->getType())) {
                $data = [...$data, ...$resolver->resolve($section)];
            }
        }

        return $data;
    }
}
