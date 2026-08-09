<?php

namespace App\Entity;

use App\Utils\Slugger;

use App\Repository\BannerCategoryRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

use Gedmo\Mapping\Annotation as Gedmo;

#[ORM\Entity(repositoryClass: BannerCategoryRepository::class)]
#[ORM\Table(name: 'bannercategory')]
class BannerCategory
{
    // Zone constants — where a banner group is displayed on the site.
    const ZONE_HERO = 'hero';

    const ZONES = [
        self::ZONE_HERO => 'Hero trang chủ',
    ];

    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    private $id;

    #[Assert\NotBlank(message: 'name.blank')]
    #[ORM\Column(name: 'name', type: Types::STRING, length: 255)]
    private $name;

    #[Assert\NotBlank(message: 'url.blank')]
    #[ORM\Column(name: 'url', type: Types::STRING, length: 255, unique: true)]
    private $url;

    #[ORM\Column(name: 'zone', type: Types::STRING, length: 50, options: ['default' => self::ZONE_HERO])]
    private $zone = self::ZONE_HERO;

    #[Gedmo\Timestampable(on: 'create')]
    #[ORM\Column(name: 'createdAt', type: Types::DATETIME_MUTABLE)]
    private $createdAt;

    #[Gedmo\Timestampable(on: 'update')]
    #[ORM\Column(name: 'updatedAt', type: Types::DATETIME_MUTABLE)]
    private $updatedAt;

    public function __toString()
    {
        return $this->name;
    }


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
     * Set name
     *
     * @param string $name
     * @return BannerCategory
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
     * @return BannerCategory
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
     * Set zone
     *
     * @param string $zone
     * @return BannerCategory
     */
    public function setZone($zone)
    {
        $this->zone = $zone;

        return $this;
    }

    /**
     * Get zone
     *
     * @return string
     */
    public function getZone()
    {
        return $this->zone;
    }

    /**
     * Set createdAt
     *
     * @param \DateTime $createdAt
     * @return BannerCategory
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
     * @return BannerCategory
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
