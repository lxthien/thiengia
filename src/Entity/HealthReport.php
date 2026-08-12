<?php

namespace App\Entity;

use App\Repository\HealthReportRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * HealthReport — kết quả HealthAuditManager::buildReport() đã tính sẵn, ghi
 * bởi app:compute-health-report (cron). Chỉ giữ 1 bản mới nhất (không cần
 * lịch sử như DailyStats) — HealthController đọc thẳng, không tự audit lại
 * (DOM parsing toàn bộ nội dung, quét thư mục upload...) lúc admin mở trang.
 */
#[ORM\Table(name: 'health_report')]
#[ORM\Entity(repositoryClass: HealthReportRepository::class)]
class HealthReport
{
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    private $id;

    #[ORM\Column(name: 'generatedAt', type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $generatedAt;

    /**
     * Toàn bộ report của buildReport() trừ generatedAt (đã có cột riêng),
     * với mọi tham chiếu News entity thay bằng mảng phẳng
     * {id, title, url, isPage} — xem ComputeHealthReportCommand.
     */
    #[ORM\Column(name: 'dataJson', type: Types::TEXT)]
    private string $dataJson = '{}';

    public function getId()
    {
        return $this->id;
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

    public function getData(): array
    {
        return json_decode($this->dataJson, true) ?: [];
    }

    public function setData(array $data): static
    {
        $this->dataJson = json_encode($data);

        return $this;
    }
}
