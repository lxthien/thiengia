<?php

namespace App\Entity;

use App\Repository\MediaAssetRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

// Media Library (MediaController) không có entity riêng — thư viện hoàn
// toàn dựa trên filesystem (quét thư mục uploads/media/). Entity này CHỈ
// lưu metadata bổ sung (alt text) theo đường dẫn tương đối của file, không
// thay thế/di chuyển hệ thống filesystem hiện có.
#[ORM\Entity(repositoryClass: MediaAssetRepository::class)]
#[ORM\Table(name: 'media_asset')]
class MediaAsset
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private $id;

    #[ORM\Column(type: Types::STRING, length: 500, unique: true)]
    private $path;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private $altText;

    #[Gedmo\Timestampable(on: 'create')]
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private $createdAt;

    #[Gedmo\Timestampable(on: 'update')]
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private $updatedAt;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function setPath(string $path): self
    {
        $this->path = $path;
        return $this;
    }

    public function getAltText(): ?string
    {
        return $this->altText;
    }

    public function setAltText(?string $altText): self
    {
        $this->altText = $altText;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }
}
