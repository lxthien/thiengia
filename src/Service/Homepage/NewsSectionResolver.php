<?php

namespace App\Service\Homepage;

use App\Entity\HomepageSection;
use App\Entity\News;
use App\Enum\PostStatus;
use App\Enum\SectionType;
use App\Service\SettingsManager;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Khối "Cẩm nang": bài viết mới nhất của danh mục được cấu hình.
 *
 * Vẫn đọc setting `listCategoryOnHomepage` như trước để không đổi nội dung
 * đang chạy. Setting này là JSON gõ tay trong textarea — Đợt 3 sẽ thay bằng
 * form chọn danh mục lưu trong HomepageSection::config.
 */
final class NewsSectionResolver implements SectionResolverInterface
{
    private const DEFAULT_LIMIT = 4;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SettingsManager $settingsManager,
    ) {
    }

    public function supports(SectionType $type): bool
    {
        return $type === SectionType::News;
    }

    public function resolve(HomepageSection $section): array
    {
        $limit = (int) $section->getConfigValue('limit', self::DEFAULT_LIMIT);

        if ($limit < 1) {
            $limit = self::DEFAULT_LIMIT;
        }

        // Danh mục chọn trong admin được ưu tiên; chưa chọn thì lùi về setting
        // cũ để trang chủ của bản chưa cấu hình lại vẫn có bài.
        $selected = self::toIds($section->getConfigValue('categories', []));

        if ($selected !== []) {
            return ['latestNews' => $this->findPublished($selected, $limit)];
        }

        foreach ($this->configuredCategoryGroups() as $categoryIds) {
            $posts = $this->findPublished($categoryIds, $limit);

            // Nhóm danh mục đầu tiên có bài sẽ được dùng — giống hành vi cũ
            // của template (lấy block đầu tiên có bài).
            if ($posts !== []) {
                return ['latestNews' => $posts];
            }
        }

        return ['latestNews' => []];
    }

    /**
     * Mỗi phần tử của setting là 1 nhóm: dùng các danh mục con nếu có khai
     * báo, ngược lại dùng chính danh mục cha.
     *
     * @return list<list<int>>
     */
    private function configuredCategoryGroups(): array
    {
        $raw = (string) $this->settingsManager->get('listCategoryOnHomepage', '');

        if (trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        if (!is_array($decoded)) {
            return [];
        }

        $groups = [];

        foreach ($decoded as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $subIds = self::toIds($entry['subId'] ?? '');
            $ids = $subIds !== [] ? $subIds : self::toIds($entry['id'] ?? '');

            if ($ids !== []) {
                $groups[] = $ids;
            }
        }

        return $groups;
    }

    /**
     * @param list<int> $categoryIds
     *
     * @return News[]
     */
    private function findPublished(array $categoryIds, int $limit): array
    {
        return $this->em->createQueryBuilder()
            ->select('n')->from(News::class, 'n')
            ->leftJoin('n.category', 'c')
            ->where('c.id IN (:categories)')
            ->andWhere('n.status = :status')
            ->setParameter('categories', $categoryIds)
            ->setParameter('status', PostStatus::Published)
            ->orderBy('n.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()->getResult();
    }

    /**
     * '10,11' hoặc 10 => [10, 11]
     *
     * @return list<int>
     */
    private static function toIds(mixed $value): array
    {
        // Chấp nhận cả mảng id (config chọn trong admin) lẫn chuỗi "10,11"
        // (setting cũ listCategoryOnHomepage).
        $parts = is_array($value) ? $value : explode(',', (string) $value);
        $ids = [];

        foreach ($parts as $part) {
            $id = (int) trim((string) $part);

            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }
}
