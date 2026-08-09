<?php

namespace App\Entity;

use App\Repository\GalleryAlbumRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Validator\Constraints as Assert;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * GalleryAlbum — nhóm nhiều ảnh cho 1 hoạt động (vd: lễ ký kết, khởi công...)
 * Hiển thị trên trang chủ dưới dạng 1 thẻ đại diện, click mở lightbox album.
 */
#[ORM\Table(name: 'gallery_album')]
#[ORM\Entity(repositoryClass: GalleryAlbumRepository::class)]
class GalleryAlbum
{
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    private $id;

    #[Assert\NotBlank(message: 'name.blank')]
    #[ORM\Column(name: 'name', type: Types::STRING, length: 255)]
    private $name;

    #[ORM\Column(name: 'description', type: Types::TEXT, nullable: true)]
    private $description;

    #[ORM\Column(name: 'position', type: Types::INTEGER, options: ['default' => 0])]
    private $position = 0;

    #[ORM\Column(name: 'enable', type: Types::BOOLEAN, options: ['default' => true])]
    private $enable = true;

    /**
     * @var ArrayCollection
     */
    #[ORM\OneToMany(targetEntity: GalleryImage::class, mappedBy: 'album', cascade: ['all'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC', 'id' => 'ASC'])]
    private $images;

    #[Gedmo\Timestampable(on: 'create')]
    #[ORM\Column(name: 'createdAt', type: Types::DATETIME_MUTABLE)]
    private $createdAt;

    #[Gedmo\Timestampable(on: 'update')]
    #[ORM\Column(name: 'updatedAt', type: Types::DATETIME_MUTABLE)]
    private $updatedAt;

    public function __construct()
    {
        $this->images = new ArrayCollection();
        $this->createdAt = new \DateTime();
    }

    public function __toString()
    {
        return (string) $this->name;
    }

    public function getId()
    {
        return $this->id;
    }

    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setDescription($description)
    {
        $this->description = $description;

        return $this;
    }

    public function getDescription()
    {
        return $this->description;
    }

    public function setPosition($position)
    {
        $this->position = $position;

        return $this;
    }

    public function getPosition()
    {
        return $this->position;
    }

    public function setEnable($enable)
    {
        $this->enable = $enable;

        return $this;
    }

    public function getEnable()
    {
        return $this->enable;
    }

    /**
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getImages()
    {
        return $this->images;
    }

    public function addImage(GalleryImage $image)
    {
        if (!$this->images->contains($image)) {
            $this->images->add($image);
            $image->setAlbum($this);
        }

        return $this;
    }

    public function removeImage(GalleryImage $image)
    {
        $this->images->removeElement($image);

        return $this;
    }

    /**
     * Ảnh đại diện của album (thứ tự đầu tiên), dùng cho thẻ trên trang chủ/admin.
     */
    public function getCoverImage(): ?GalleryImage
    {
        return $this->images->first() ?: null;
    }

    public function setCreatedAt($createdAt)
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    public function setUpdatedAt($updatedAt)
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }
}
