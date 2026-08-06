<?php

namespace App\Entity;

use App\Repository\MenuItemRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Validator\Constraints as Assert;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * MenuItem
 */
#[ORM\Table(name: 'menu_item', options: ['collate' => 'utf8_general_ci'])]
#[ORM\Entity(repositoryClass: MenuItemRepository::class)]
class MenuItem
{
    const TYPE_URL = 'url';
    const TYPE_NEWS = 'news';
    const TYPE_CATEGORY = 'category';
    const TYPE_PAGE = 'page';

    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    private $id;

    /**
     * @var Menu
     */
    #[ORM\ManyToOne(targetEntity: Menu::class, inversedBy: 'items')]
    #[ORM\JoinColumn(name: 'menu_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private $menu;

    /**
     * @var MenuItem
     */
    #[ORM\ManyToOne(targetEntity: MenuItem::class, inversedBy: 'children')]
    #[ORM\JoinColumn(name: 'parent_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private $parent;

    /**
     * @var ArrayCollection
     */
    #[ORM\OneToMany(targetEntity: MenuItem::class, mappedBy: 'parent', cascade: ['all'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC', 'id' => 'ASC'])]
    private $children;

    #[Assert\NotBlank(message: 'title.blank')]
    #[Assert\Length(
        min: 2,
        max: 255,
        minMessage: 'Your title must be at least {{ limit }} characters long',
        maxMessage: 'Your title cannot be longer than {{ limit }} characters'
    )]
    #[ORM\Column(name: 'title', type: Types::STRING, length: 255)]
    private $title;

    #[ORM\Column(name: 'type', type: Types::STRING, length: 50)]
    private $type;

    #[ORM\Column(name: 'url', type: Types::STRING, length: 500, nullable: true)]
    private $url;

    #[ORM\Column(name: 'target_id', type: Types::INTEGER, nullable: true)]
    private $targetId;

    #[ORM\Column(name: 'target_type', type: Types::STRING, length: 50, nullable: true)]
    private $targetType;

    #[ORM\Column(name: 'css_class', type: Types::STRING, length: 255, nullable: true)]
    private $cssClass;

    /**
     * @var string (For SEO: e.g., '_blank', '_self')
     */
    #[ORM\Column(name: 'target_attr', type: Types::STRING, length: 20, nullable: true)]
    private $targetAttr = '_self';

    #[ORM\Column(name: 'title_attr', type: Types::STRING, length: 255, nullable: true)]
    private $titleAttr;

    #[ORM\Column(name: 'position', type: Types::INTEGER, options: ['default' => 0])]
    private $position = 0;

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
        $this->children = new ArrayCollection();
        $this->createdAt = new \DateTime();
        $this->type = self::TYPE_URL;
    }

    public function __toString()
    {
        return $this->title;
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
     * Set menu
     *
     * @param Menu $menu
     * @return MenuItem
     */
    public function setMenu(Menu $menu = null)
    {
        $this->menu = $menu;

        return $this;
    }

    /**
     * Get menu
     *
     * @return Menu
     */
    public function getMenu()
    {
        return $this->menu;
    }

    /**
     * Set parent
     *
     * @param MenuItem $parent
     * @return MenuItem
     */
    public function setParent(MenuItem $parent = null)
    {
        $this->parent = $parent;

        return $this;
    }

    /**
     * Get parent
     *
     * @return MenuItem
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * Add children
     *
     * @param MenuItem $children
     * @return MenuItem
     */
    public function addChild(MenuItem $children)
    {
        $this->children[] = $children;

        return $this;
    }

    /**
     * Remove children
     *
     * @param MenuItem $children
     */
    public function removeChild(MenuItem $children)
    {
        $this->children->removeElement($children);
    }

    /**
     * Get children
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getChildren()
    {
        return $this->children;
    }

    /**
     * Set title
     *
     * @param string $title
     * @return MenuItem
     */
    public function setTitle($title)
    {
        $this->title = $title;

        return $this;
    }

    /**
     * Get title
     *
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * Set type
     *
     * @param string $type
     * @return MenuItem
     */
    public function setType($type)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Get type
     *
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Set url
     *
     * @param string $url
     * @return MenuItem
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
     * Set targetId
     *
     * @param int $targetId
     * @return MenuItem
     */
    public function setTargetId($targetId)
    {
        $this->targetId = $targetId;

        return $this;
    }

    /**
     * Get targetId
     *
     * @return int
     */
    public function getTargetId()
    {
        return $this->targetId;
    }

    /**
     * Set targetType
     *
     * @param string $targetType
     * @return MenuItem
     */
    public function setTargetType($targetType)
    {
        $this->targetType = $targetType;

        return $this;
    }

    /**
     * Get targetType
     *
     * @return string
     */
    public function getTargetType()
    {
        return $this->targetType;
    }

    /**
     * Set cssClass
     *
     * @param string $cssClass
     * @return MenuItem
     */
    public function setCssClass($cssClass)
    {
        $this->cssClass = $cssClass;

        return $this;
    }

    /**
     * Get cssClass
     *
     * @return string
     */
    public function getCssClass()
    {
        return $this->cssClass;
    }

    /**
     * Set targetAttr (for SEO: _blank, _self, etc.)
     *
     * @param string $targetAttr
     * @return MenuItem
     */
    public function setTargetAttr($targetAttr)
    {
        $this->targetAttr = $targetAttr;

        return $this;
    }

    /**
     * Get targetAttr
     *
     * @return string
     */
    public function getTargetAttr()
    {
        return $this->targetAttr;
    }

    /**
     * Set titleAttr (for SEO: hover title)
     *
     * @param string $titleAttr
     * @return MenuItem
     */
    public function setTitleAttr($titleAttr)
    {
        $this->titleAttr = $titleAttr;

        return $this;
    }

    /**
     * Get titleAttr
     *
     * @return string
     */
    public function getTitleAttr()
    {
        return $this->titleAttr;
    }

    /**
     * Set position
     *
     * @param integer $position
     * @return MenuItem
     */
    public function setPosition($position)
    {
        $this->position = $position;

        return $this;
    }

    /**
     * Get position
     *
     * @return integer
     */
    public function getPosition()
    {
        return $this->position;
    }

    /**
     * Set enable
     *
     * @param boolean $enable
     * @return MenuItem
     */
    public function setEnable($enable)
    {
        $this->enable = $enable;

        return $this;
    }

    /**
     * Get enable
     *
     * @return boolean
     */
    public function getEnable()
    {
        return $this->enable;
    }

    /**
     * Set createdAt
     *
     * @param \DateTime $createdAt
     * @return MenuItem
     */
    public function setCreatedAt($createdAt)
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
     * @return MenuItem
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

    /**
     * Get the actual URL for this menu item
     *
     * @return string
     */
    public function getResolvedUrl()
    {
        if ($this->type === self::TYPE_URL) {
            return $this->url;
        }

        // For other types, the URL would be resolved from the target entity
        return $this->url;
    }
}
