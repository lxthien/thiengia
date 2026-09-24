<?php

namespace App\Entity;

use App\Entity\Contract\SortableRow;
use App\Repository\ServiceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Dịch vụ của công ty — khối "Dịch vụ" trên trang chủ và danh sách nhu cầu
 * trong form báo giá nhanh (App\Form\QuickQuoteType) cùng đọc từ đây.
 *
 * Trước đây nội dung nằm ở config/packages/v3.yaml và bị gõ lại lần hai trong
 * HomepageController, hai chỗ phải tự nhớ đồng bộ với nhau.
 */
#[ORM\Table(name: 'service')]
#[ORM\UniqueConstraint(name: 'uniq_service_slug', columns: ['slug'])]
#[ORM\Entity(repositoryClass: ServiceRepository::class)]
class Service implements SortableRow
{
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    private $id;

    #[Assert\NotBlank(message: 'Vui lòng nhập tên dịch vụ.')]
    #[Assert\Length(max: 120)]
    #[ORM\Column(name: 'name', type: Types::STRING, length: 120)]
    private ?string $name = null;

    /** Để dành cho trang dịch vụ riêng; hiện chưa có route nào dùng. */
    #[Assert\Length(max: 150)]
    #[ORM\Column(name: 'slug', type: Types::STRING, length: 150, nullable: true)]
    private ?string $slug = null;

    /**
     * URL ảnh, chọn từ Media Library (vd "/uploads/media/x.jpg") hoặc đường dẫn
     * asset của theme (vd "assets/images/v3/svc-1.jpg"). Không qua Vich.
     */
    #[Assert\Length(max: 255)]
    #[ORM\Column(name: 'image', type: Types::STRING, length: 255, nullable: true)]
    private ?string $image = null;

    #[Assert\NotBlank(message: 'Vui lòng nhập mô tả ngắn.')]
    #[Assert\Length(max: 400)]
    #[ORM\Column(name: 'description', type: Types::TEXT)]
    private ?string $description = null;

    /** Ví dụ: "từ 7,5 triệu/m²". Để trống thì trang chủ không hiện dòng này. */
    #[Assert\Length(max: 120)]
    #[ORM\Column(name: 'priceFrom', type: Types::STRING, length: 120, nullable: true)]
    private ?string $priceFrom = null;

    #[Assert\Length(max: 255)]
    #[ORM\Column(name: 'metaTitle', type: Types::STRING, length: 255, nullable: true)]
    private ?string $metaTitle = null;

    #[Assert\Length(max: 500)]
    #[ORM\Column(name: 'metaDescription', type: Types::TEXT, nullable: true)]
    private ?string $metaDescription = null;

    /**
     * Có thẻ riêng ở khối "Dịch vụ" trang chủ hay không. Dịch vụ tắt cờ này
     * vẫn xuất hiện trong ô "Nhu cầu" của form báo giá.
     */
    #[ORM\Column(name: 'featuredOnHome', type: Types::BOOLEAN, options: ['default' => true])]
    private bool $featuredOnHome = true;

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

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(?string $slug): self
    {
        $this->slug = $slug === null || trim($slug) === '' ? null : trim($slug);

        return $this;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): self
    {
        $this->image = $image === null || trim($image) === '' ? null : trim($image);

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getPriceFrom(): ?string
    {
        return $this->priceFrom;
    }

    public function setPriceFrom(?string $priceFrom): self
    {
        $this->priceFrom = $priceFrom === null || trim($priceFrom) === '' ? null : trim($priceFrom);

        return $this;
    }

    public function getMetaTitle(): ?string
    {
        return $this->metaTitle;
    }

    public function setMetaTitle(?string $metaTitle): self
    {
        $this->metaTitle = $metaTitle;

        return $this;
    }

    public function getMetaDescription(): ?string
    {
        return $this->metaDescription;
    }

    public function setMetaDescription(?string $metaDescription): self
    {
        $this->metaDescription = $metaDescription;

        return $this;
    }

    public function getFeaturedOnHome(): bool
    {
        return $this->featuredOnHome;
    }

    public function setFeaturedOnHome(bool $featuredOnHome): self
    {
        $this->featuredOnHome = $featuredOnHome;

        return $this;
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
}
