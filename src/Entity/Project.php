<?php

namespace App\Entity;

use App\Entity\Contract\SortableRow;
use App\Repository\ProjectRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Công trình đã bàn giao — khối "Công trình thực tế" trên trang chủ.
 *
 * Tách riêng khỏi News vì đây là dữ liệu có nghiệp vụ (diện tích, số tầng,
 * địa điểm) chứ không phải bài viết, và sẽ còn dùng cho trang danh sách công
 * trình lẫn schema markup sau này.
 */
#[ORM\Table(name: 'project')]
#[ORM\UniqueConstraint(name: 'uniq_project_slug', columns: ['slug'])]
#[ORM\Entity(repositoryClass: ProjectRepository::class)]
class Project implements SortableRow
{
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    private $id;

    #[Assert\NotBlank(message: 'Vui lòng nhập tên công trình.')]
    #[Assert\Length(max: 150)]
    #[ORM\Column(name: 'name', type: Types::STRING, length: 150)]
    private ?string $name = null;

    /** Để dành cho trang chi tiết công trình; hiện chưa có route nào dùng. */
    #[Assert\Length(max: 180)]
    #[ORM\Column(name: 'slug', type: Types::STRING, length: 180, nullable: true)]
    private ?string $slug = null;

    /** Dòng thông số dưới tên, vd "Đất 10×20m · 3 tầng · Trọn gói 850 m² sàn". */
    #[Assert\NotBlank(message: 'Vui lòng nhập dòng thông số.')]
    #[Assert\Length(max: 255)]
    #[ORM\Column(name: 'spec', type: Types::STRING, length: 255)]
    private ?string $spec = null;

    /** URL ảnh đại diện, chọn từ Media Library. Không qua Vich. */
    #[Assert\NotBlank(message: 'Vui lòng chọn ảnh đại diện.')]
    #[Assert\Length(max: 255)]
    #[ORM\Column(name: 'coverImage', type: Types::STRING, length: 255)]
    private ?string $coverImage = null;

    /** Nơi bấm vào thẻ công trình sẽ dẫn tới. Để trống thì thẻ không có link. */
    #[Assert\Length(max: 255)]
    #[ORM\Column(name: 'linkUrl', type: Types::STRING, length: 255, nullable: true)]
    private ?string $linkUrl = null;

    /** Album ảnh chi tiết, dùng cho trang chi tiết về sau. */
    #[ORM\ManyToOne(targetEntity: GalleryAlbum::class)]
    #[ORM\JoinColumn(name: 'album_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?GalleryAlbum $album = null;

    #[Assert\Length(max: 60)]
    #[ORM\Column(name: 'area', type: Types::STRING, length: 60, nullable: true)]
    private ?string $area = null;

    #[Assert\Length(max: 60)]
    #[ORM\Column(name: 'floors', type: Types::STRING, length: 60, nullable: true)]
    private ?string $floors = null;

    #[Assert\Length(max: 120)]
    #[ORM\Column(name: 'location', type: Types::STRING, length: 120, nullable: true)]
    private ?string $location = null;

    #[ORM\Column(name: 'featuredOnHome', type: Types::BOOLEAN, options: ['default' => true])]
    private bool $featuredOnHome = true;

    #[Assert\Length(max: 255)]
    #[ORM\Column(name: 'metaTitle', type: Types::STRING, length: 255, nullable: true)]
    private ?string $metaTitle = null;

    #[Assert\Length(max: 500)]
    #[ORM\Column(name: 'metaDescription', type: Types::TEXT, nullable: true)]
    private ?string $metaDescription = null;

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

    public function getSpec(): ?string
    {
        return $this->spec;
    }

    public function setSpec(?string $spec): self
    {
        $this->spec = $spec;

        return $this;
    }

    public function getCoverImage(): ?string
    {
        return $this->coverImage;
    }

    public function setCoverImage(?string $coverImage): self
    {
        $this->coverImage = $coverImage === null || trim($coverImage) === '' ? null : trim($coverImage);

        return $this;
    }

    public function getLinkUrl(): ?string
    {
        return $this->linkUrl;
    }

    public function setLinkUrl(?string $linkUrl): self
    {
        $this->linkUrl = $linkUrl === null || trim($linkUrl) === '' ? null : trim($linkUrl);

        return $this;
    }

    public function getAlbum(): ?GalleryAlbum
    {
        return $this->album;
    }

    public function setAlbum(?GalleryAlbum $album): self
    {
        $this->album = $album;

        return $this;
    }

    public function getArea(): ?string
    {
        return $this->area;
    }

    public function setArea(?string $area): self
    {
        $this->area = $area;

        return $this;
    }

    public function getFloors(): ?string
    {
        return $this->floors;
    }

    public function setFloors(?string $floors): self
    {
        $this->floors = $floors;

        return $this;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(?string $location): self
    {
        $this->location = $location;

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
