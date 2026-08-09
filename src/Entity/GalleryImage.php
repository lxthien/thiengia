<?php

namespace App\Entity;

use App\Repository\GalleryImageRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * GalleryImage — 1 ảnh trong 1 GalleryAlbum, chọn qua Media Library (không Vich).
 */
#[ORM\Table(name: 'gallery_image')]
#[ORM\Entity(repositoryClass: GalleryImageRepository::class)]
class GalleryImage
{
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    private $id;

    /**
     * @var GalleryAlbum
     */
    #[ORM\ManyToOne(targetEntity: GalleryAlbum::class, inversedBy: 'images')]
    #[ORM\JoinColumn(name: 'album_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private $album;

    /**
     * Full root-relative URL của ảnh, chọn qua Media Library picker (vd "/uploads/media/xxx.jpg").
     */
    #[Assert\NotBlank]
    #[ORM\Column(name: 'imageUrl', type: Types::STRING, length: 500)]
    private $imageUrl;

    #[ORM\Column(name: 'caption', type: Types::STRING, length: 255, nullable: true)]
    private $caption;

    #[ORM\Column(name: 'alt', type: Types::STRING, length: 255, nullable: true)]
    private $alt;

    #[ORM\Column(name: 'position', type: Types::INTEGER, options: ['default' => 0])]
    private $position = 0;

    #[Gedmo\Timestampable(on: 'create')]
    #[ORM\Column(name: 'createdAt', type: Types::DATETIME_MUTABLE)]
    private $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId()
    {
        return $this->id;
    }

    public function setAlbum(GalleryAlbum $album = null)
    {
        $this->album = $album;

        return $this;
    }

    public function getAlbum()
    {
        return $this->album;
    }

    public function setImageUrl($imageUrl)
    {
        $this->imageUrl = $imageUrl;

        return $this;
    }

    public function getImageUrl()
    {
        return $this->imageUrl;
    }

    public function setCaption($caption)
    {
        $this->caption = $caption;

        return $this;
    }

    public function getCaption()
    {
        return $this->caption;
    }

    public function setAlt($alt)
    {
        $this->alt = $alt;

        return $this;
    }

    public function getAlt()
    {
        return $this->alt;
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

    public function setCreatedAt($createdAt)
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getCreatedAt()
    {
        return $this->createdAt;
    }
}
