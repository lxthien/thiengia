<?php

namespace App\EventListener;

use App\Entity\News;
use App\Enum\PostStatus;
use App\Seo\IndexNow\IndexNowClient;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Tự báo IndexNow khi một bài viết/trang công khai được đăng, sửa nội dung,
 * đổi URL, gỡ xuống hoặc xóa.
 *
 * URL được gom lại trong lúc Doctrine flush và chỉ gửi đi ở kernel.terminate
 * (sau khi trình duyệt đã nhận response) hoặc console.terminate (lệnh
 * app:publish-scheduled), nên thao tác lưu bài trong admin không bị chậm đi.
 */
#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postUpdate)]
#[AsDoctrineListener(event: Events::postRemove)]
final class IndexNowListener implements EventSubscriberInterface
{
    /**
     * Chỉ những thay đổi này mới đáng báo lại; lưu bài mà không đổi gì trong
     * số này (vd chỉ đổi thứ tự hiển thị) thì bỏ qua.
     */
    private const RELEVANT_FIELDS = [
        'title', 'url', 'contents', 'description', 'pageTitle', 'pageDescription',
        'images', 'status', 'metaIndex', 'publishedAt',
    ];

    /** @var array<string, true> */
    private array $pending = [];

    public function __construct(
        private readonly IndexNowClient $client,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::TERMINATE => 'flush',
            ConsoleEvents::TERMINATE => 'flush',
        ];
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        $entity = $args->getObject();

        if ($entity instanceof News && $this->isPublic($entity)) {
            $this->queue($entity->getUrl());
        }
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$entity instanceof News) {
            return;
        }

        $changes = $args->getObjectManager()->getUnitOfWork()->getEntityChangeSet($entity);

        if (array_intersect(self::RELEVANT_FIELDS, array_keys($changes)) === []) {
            return;
        }

        // Giá trị cũ của status trong changeset có thể là enum hoặc chuỗi gốc
        // tùy phiên bản Doctrine — so theo giá trị chuỗi cho chắc.
        $oldStatus = $changes['status'][0] ?? $entity->getStatus();
        $oldStatus = $oldStatus instanceof PostStatus ? $oldStatus->value : (string) $oldStatus;

        $wasPublic = $oldStatus === PostStatus::Published->value
            && (bool) ($changes['metaIndex'][0] ?? $entity->isMetaIndex());

        // Bài vừa bị gỡ/noindex cũng cần báo để công cụ tìm kiếm thu thập lại
        // và bỏ khỏi chỉ mục; bài chưa từng công khai thì không cần.
        if (!$wasPublic && !$this->isPublic($entity)) {
            return;
        }

        $this->queue($entity->getUrl());

        // Đổi slug: báo cả URL cũ để nó được thu thập lại (thấy redirect/404).
        if (isset($changes['url'][0]) && $wasPublic) {
            $this->queue((string) $changes['url'][0]);
        }
    }

    public function postRemove(PostRemoveEventArgs $args): void
    {
        $entity = $args->getObject();

        if ($entity instanceof News && $this->isPublic($entity)) {
            $this->queue($entity->getUrl());
        }
    }

    public function flush(): void
    {
        if ($this->pending === []) {
            return;
        }

        $urls = array_keys($this->pending);
        $this->pending = [];

        try {
            $this->client->submit($urls);
        } catch (\Throwable) {
            // Thông báo cho công cụ tìm kiếm là việc phụ: không để lỗi ở đây
            // làm hỏng request hay lệnh console đang kết thúc.
        }
    }

    private function isPublic(News $news): bool
    {
        return $news->getStatus() === PostStatus::Published && $news->isMetaIndex();
    }

    private function queue(?string $slug): void
    {
        $slug = trim((string) $slug, '/');

        if ($slug === '' || !$this->client->isEnabled()) {
            return;
        }

        // Cùng dạng với URL trong sitemap (SitemapService::generateNewsUrl).
        $base = rtrim($this->urlGenerator->generate('homepage', [], UrlGeneratorInterface::ABSOLUTE_URL), '/');
        $this->pending[$base . '/' . $slug . '/'] = true;
    }
}
