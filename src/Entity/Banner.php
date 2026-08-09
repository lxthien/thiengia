<?php

namespace App\Entity;

use App\Repository\BannerRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

use Gedmo\Mapping\Annotation as Gedmo;

#[ORM\Table(name: 'banner')]
#[ORM\Entity(repositoryClass: BannerRepository::class)]
class Banner
{
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    private $id;

    /**
     * @var BannerCategory
     */
    #[ORM\ManyToOne(targetEntity: BannerCategory::class)]
    #[ORM\JoinColumn(name: 'bannercategory_id', referencedColumnName: 'id')]
    private $bannercategory;

    #[ORM\Column(name: 'position', type: Types::INTEGER, nullable: true)]
    private $position;

    #[ORM\Column(name: 'name', type: Types::STRING, length: 255, nullable: true)]
    private $name;

    #[ORM\Column(name: 'url', type: Types::STRING, length: 255, nullable: true)]
    private $url;

    /**
     * Full root-relative URL of the image, chosen via the Media Library picker
     * (e.g. "/uploads/media/xxx.jpg"). Not a Vich-managed field.
     */
    #[Assert\NotBlank(message: 'Vui lòng chọn ảnh banner từ Media Library.')]
    #[ORM\Column(name: 'urlImage', type: Types::STRING, length: 255)]
    private $urlImage;

    #[ORM\Column(name: 'enable', type: Types::BOOLEAN, options: ['default' => true])]
    private $enable = true;

    #[Gedmo\Timestampable(on: 'create')]
    #[ORM\Column(name: 'createdAt', type: Types::DATETIME_MUTABLE)]
    private $createdAt;

    #[Gedmo\Timestampable(on: 'update')]
    #[ORM\Column(name: 'updatedAt', type: Types::DATETIME_MUTABLE)]
    private $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    /**
     * Get id
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set banner category
     *
     * @param \App\Entity\BannerCategory $bannercategory
     * @return Banner
     */
    public function setBannerCategory(\App\Entity\BannerCategory $bannercategory)
    {
        $this->bannercategory = $bannercategory;

        return $this;
    }

    /**
     * Get banner category
     *
     * @return \App\Entity\BannerCategory 
     */
    public function getBannerCategory()
    {
        return $this->bannercategory;
    }

    /**
     * Set position
     *
     * @param int $position
     * @return Banner
     */
    public function setPosition($position)
    {
        $this->position = $position;

        return $this;
    }

    /**
     * Get position
     *
     * @return int
     */
    public function getPosition()
    {
        return $this->position;
    }

    /**
     * Set name
     *
     * @param string $name
     * @return Banner
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get name
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set url
     *
     * @param string $url
     * @return Banner
     */
    public function setUrl($url)
    {
        $this->url = $url;

        return $this;
    }

    /**
     * Get url
     *
     * @return string
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * Set urlImage
     *
     * @param string $urlImage
     * @return Banner
     */
    public function setUrlImage($urlImage)
    {
        $this->urlImage = $urlImage;

        return $this;
    }

    /**
     * Get urlImage
     *
     * @return string
     */
    public function getUrlImage()
    {
        return $this->urlImage;
    }

    /**
     * Set enable
     *
     * @param bool $enable
     * @return Banner
     */
    public function setEnable($enable)
    {
        $this->enable = $enable;

        return $this;
    }

    /**
     * Get enable
     *
     * @return bool
     */
    public function getEnable()
    {
        return $this->enable;
    }

    /**
     * Set createdAt
     *
     * @param \DateTime $createdAt
     * @return Banner
     */
    public function setcreatedAt($createdAt)
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * Get createdAt
     *
     * @return \DateTime
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * Set updatedAt
     *
     * @param \DateTime $updatedAt
     * @return Banner
     */
    public function setUpdatedAt($updatedAt)
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /**
     * Get updatedAt
     *
     * @return \DateTime
     */
    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }
}
