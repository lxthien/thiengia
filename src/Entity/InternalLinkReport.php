<?php

namespace App\Entity;

use App\Repository\InternalLinkReportRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * InternalLinkReport — kết quả InternalLinkAnalyzer::analyze() đã tính sẵn,
 * ghi bởi app:compute-internal-links (cron) hoặc nút "Phân tích lại". Chỉ giữ
 * 1 bản mới nhất, giống HealthReport — dashboard đọc thẳng, không tự quét lại
 * toàn bộ nội dung lúc admin mở trang.
 */
#[ORM\Table(name: 'internal_link_report')]
#[ORM\Entity(repositoryClass: InternalLinkReportRepository::class)]
class InternalLinkReport
{
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    private $id;

    #[ORM\Column(name: 'generatedAt', type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $generatedAt;

    // MEDIUMTEXT: danh sách link của site vài nghìn bài vượt quá 64KB của TEXT.
    #[ORM\Column(name: 'dataJson', type: Types::TEXT, length: 16777215)]
    private string $dataJson = '{}';

    public function __construct()
    {
        $this->generatedAt = new \DateTime();
    }

    public function getId()
    {
        return $this->id;
    }

    public function getGeneratedAt(): \DateTimeInterface
    {
        return $this->generatedAt;
    }

    public function getData(): array
    {
        return json_decode($this->dataJson, true) ?: [];
    }

    public function setData(array $data): static
    {
        $this->dataJson = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);

        return $this;
    }
}
