<?php

namespace App\Entity;

use App\Enum\SectionType;
use App\Entity\Contract\SortableRow;
use App\Repository\HomepageSectionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Một khối trên trang chủ: thứ tự, bật/tắt và phần chữ do admin sửa.
 *
 * Dữ liệu lặp của khối (danh sách dịch vụ, công trình, đánh giá...) KHÔNG
 * nằm ở đây — mỗi khối tự lấy từ entity/cấu hình riêng qua
 * App\Service\Homepage\HomepageDataProvider.
 *
 * Ba ô chữ đều nullable có chủ ý: để trống thì template dùng nội dung mặc
 * định của theme, nên trang chủ không bao giờ hiện khối trắng.
 */
#[ORM\Table(name: 'homepage_section')]
#[ORM\UniqueConstraint(name: 'uniq_homepage_section_type', columns: ['type'])]
#[ORM\Entity(repositoryClass: HomepageSectionRepository::class)]
class HomepageSection implements SortableRow
{
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    private $id;

    #[ORM\Column(name: 'type', type: Types::STRING, length: 50, enumType: SectionType::class)]
    private SectionType $type;

    /** Chữ nhỏ phía trên tiêu đề (class "sec-label"). */
    #[Assert\Length(max: 255)]
    #[ORM\Column(name: 'label', type: Types::STRING, length: 255, nullable: true)]
    private ?string $label = null;

    /** Tiêu đề lớn. Cho phép <em> để giữ điểm nhấn của theme. */
    #[Assert\Length(max: 255)]
    #[ORM\Column(name: 'title', type: Types::STRING, length: 255, nullable: true)]
    private ?string $title = null;

    #[Assert\Length(max: 2000)]
    #[ORM\Column(name: 'subtitle', type: Types::TEXT, nullable: true)]
    private ?string $subtitle = null;

    /**
     * Cấu hình riêng theo từng loại khối (vd: số bài viết hiển thị).
     *
     * @var array<string, mixed>|null
     */
    #[ORM\Column(name: 'config', type: Types::JSON, nullable: true)]
    private ?array $config = null;

    #[ORM\Column(name: 'position', type: Types::INTEGER, options: ['default' => 0])]
    private int $position = 0;

    #[ORM\Column(name: 'enable', type: Types::BOOLEAN, options: ['default' => true])]
    private bool $enable = true;

    #[Gedmo\Timestampable(on: 'create')]
    #[ORM\Column(name: 'createdAt', type: Types::DATETIME_MUTABLE)]
    private $createdAt;

    #[Gedmo\Timestampable(on: 'update')]
    #[ORM\Column(name: 'updatedAt', type: Types::DATETIME_MUTABLE)]
    private $updatedAt;

    public function __construct(?SectionType $type = null)
    {
        if ($type !== null) {
            $this->type = $type;
        }

        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): SectionType
    {
        return $this->type;
    }

    public function setType(SectionType $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): self
    {
        $this->label = self::normalize($label);

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): self
    {
        $this->title = self::normalize($title);

        return $this;
    }

    public function getSubtitle(): ?string
    {
        return $this->subtitle;
    }

    public function setSubtitle(?string $subtitle): self
    {
        $this->subtitle = self::normalize($subtitle);

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        return $this->config ?? [];
    }

    /**
     * @param array<string, mixed>|null $config
     */
    public function setConfig(?array $config): self
    {
        $this->config = $config === null || $config === [] ? null : $config;

        return $this;
    }

    public function getConfigValue(string $key, mixed $default = null): mixed
    {
        return $this->getConfig()[$key] ?? $default;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): self
    {
        $this->position = $position;

        return $this;
    }

    public function getEnable(): bool
    {
        return $this->enable;
    }

    public function setEnable(bool $enable): self
    {
        $this->enable = $enable;

        return $this;
    }

    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }

    /**
     * Tên hiển thị trong admin và trong log hoạt động.
     */
    public function getName(): string
    {
        return $this->type->label();
    }

    /**
     * Ô để trống phải thành NULL chứ không phải chuỗi rỗng, để template
     * `|default()` nhận ra là "chưa nhập" và dùng nội dung mặc định.
     */
    private static function normalize(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
