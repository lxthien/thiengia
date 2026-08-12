<?php

namespace App\Entity;

use App\Repository\ContentDecaySnapshotRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * ContentDecaySnapshot — kết quả ContentDecayReporter::analyze() đã tính sẵn
 * cho 1 bài viết, ghi bởi app:compute-content-decay (cron). Admin xem danh
 * sách Content Decay đọc từ bảng này, không tự tính lại lúc request.
 */
// PK là id tự tăng riêng (không dùng news_id làm khóa chính trực tiếp) —
// Doctrine's Paginator (KnpPaginator dùng bên trong) không hỗ trợ phân trang
// entity có khóa chính là khóa ngoại nếu không bật Output Walkers, xem
// https://github.com/doctrine/orm/issues thảo luận LimitSubqueryWalker.
// unique constraint vẫn đảm bảo 1 News chỉ có tối đa 1 snapshot.
#[ORM\Table(name: 'content_decay_snapshot')]
#[ORM\UniqueConstraint(name: 'uniq_content_decay_snapshot_news', columns: ['news_id'])]
#[ORM\Entity(repositoryClass: ContentDecaySnapshotRepository::class)]
class ContentDecaySnapshot
{
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    private $id;

    #[ORM\OneToOne(targetEntity: News::class)]
    #[ORM\JoinColumn(name: 'news_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private News $news;

    #[ORM\Column(name: 'decayScore', type: Types::INTEGER)]
    private int $decayScore = 0;

    #[ORM\Column(name: 'decayStatus', type: Types::STRING, length: 50)]
    private string $decayStatus = '';

    #[ORM\Column(name: 'ageDays', type: Types::INTEGER)]
    private int $ageDays = 0;

    #[ORM\Column(name: 'views', type: Types::INTEGER)]
    private int $views = 0;

    #[ORM\Column(name: 'seoScore', type: Types::INTEGER)]
    private int $seoScore = 0;

    #[ORM\Column(name: 'seoStatus', type: Types::STRING, length: 50)]
    private string $seoStatus = '';

    #[ORM\Column(name: 'reasonsJson', type: Types::TEXT)]
    private string $reasonsJson = '[]';

    #[ORM\Column(name: 'recommendationsJson', type: Types::TEXT)]
    private string $recommendationsJson = '[]';

    #[ORM\Column(name: 'isIndexable', type: Types::BOOLEAN)]
    private bool $isIndexable = true;

    #[ORM\Column(name: 'generatedAt', type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $generatedAt;

    public function __construct(News $news)
    {
        $this->news = $news;
        $this->generatedAt = new \DateTime();
    }

    public function getId()
    {
        return $this->id;
    }

    public function getNews(): News
    {
        return $this->news;
    }

    public function getDecayScore(): int
    {
        return $this->decayScore;
    }

    public function setDecayScore(int $decayScore): static
    {
        $this->decayScore = $decayScore;

        return $this;
    }

    public function getDecayStatus(): string
    {
        return $this->decayStatus;
    }

    public function setDecayStatus(string $decayStatus): static
    {
        $this->decayStatus = $decayStatus;

        return $this;
    }

    public function getAgeDays(): int
    {
        return $this->ageDays;
    }

    public function setAgeDays(int $ageDays): static
    {
        $this->ageDays = $ageDays;

        return $this;
    }

    public function getViews(): int
    {
        return $this->views;
    }

    public function setViews(int $views): static
    {
        $this->views = $views;

        return $this;
    }

    public function getSeoScore(): int
    {
        return $this->seoScore;
    }

    public function setSeoScore(int $seoScore): static
    {
        $this->seoScore = $seoScore;

        return $this;
    }

    public function getSeoStatus(): string
    {
        return $this->seoStatus;
    }

    public function setSeoStatus(string $seoStatus): static
    {
        $this->seoStatus = $seoStatus;

        return $this;
    }

    public function getReasons(): array
    {
        return json_decode($this->reasonsJson, true) ?: [];
    }

    public function setReasons(array $reasons): static
    {
        $this->reasonsJson = json_encode(array_values($reasons));

        return $this;
    }

    public function getRecommendations(): array
    {
        return json_decode($this->recommendationsJson, true) ?: [];
    }

    public function setRecommendations(array $recommendations): static
    {
        $this->recommendationsJson = json_encode(array_values($recommendations));

        return $this;
    }

    public function isIndexable(): bool
    {
        return $this->isIndexable;
    }

    public function setIsIndexable(bool $isIndexable): static
    {
        $this->isIndexable = $isIndexable;

        return $this;
    }

    public function getGeneratedAt(): \DateTimeInterface
    {
        return $this->generatedAt;
    }

    public function setGeneratedAt(\DateTimeInterface $generatedAt): static
    {
        $this->generatedAt = $generatedAt;

        return $this;
    }
}
