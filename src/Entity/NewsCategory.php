<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\HttpFoundation\File\File;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * NewsCategory
 *
 * @ORM\Table(name="newscategory", options={"collate"="utf8_general_ci"})
 * @ORM\Entity(repositoryClass="App\Repository\NewsCategoryRepository")
 */
class NewsCategory
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * One Category has Many Categories.
     * @ORM\OneToMany(targetEntity="App\Entity\NewsCategory", mappedBy="parentcat")
     */
    protected $children;

    /**
     * Many Categories have One Category.
     * @ORM\ManyToOne(targetEntity="App\Entity\NewsCategory", inversedBy="children")
     * @ORM\JoinColumn(name="parentcat_id", referencedColumnName="id", nullable=true)
     */
    private $parentcat;

    /**
     * @var string
     *
     * @ORM\Column(name="name", type="string", length=255)
     */
    private $name;

    /**
     * @var string
     *
     * @ORM\Column(name="titleLandingPage", type="string", length=255, nullable=true)
     */
    private $titleLandingPage;

    /**
     * @var string
     *
     * @ORM\Column(name="url", type="string", length=255)
     */
    private $url;

    /**
     * @var string
     *
     * @ORM\Column(name="urlReplace", type="string", length=255, nullable=true)
     */
    private $urlReplace;

    /**
     * @var string
     *
     * @ORM\Column(name="description", type="text", nullable=true)
     */
    private $description = null;

    /**
     * @var string
     *
     * @ORM\Column(name="content", type="text", nullable=true)
     */
    private $content = null;

    /**
     * @var boolean
     *
     * @ORM\Column(name="enable", type="boolean")
     */
    private $enable = true;

    /**
     * @var boolean
     *
     * @ORM\Column(name="showPostRelated", type="boolean")
     */
    private $showPostRelated = false;

    /**
     * @var string
     *
     * @ORM\Column(name="robots", type="string", length=255, nullable=true)
     */
    private $robots = null;

    /**
     * @var string
     *
     * @ORM\Column(name="pageTitle", type="string", length=255, nullable=true)
     */
    private $pageTitle = null;

    /**
     * @var string
     *
     * @ORM\Column(name="pageDescription", type="text", nullable=true)
     */
    private $pageDescription = null;

    /**
     * @var string
     *
     * @ORM\Column(name="pageKeyword", type="string", length=255, nullable=true)
     */
    private $pageKeyword = null;

    /**
     * @var string
     *
     * @ORM\Column(name="sortBy", type="string", length=255, nullable=true)
     */
    private $sortBy = null;

    /**
     * @var string
     *
     * @ORM\Column(name="schemaMarkup", type="text", nullable=true)
     */
    private $schemaMarkup = null;

    /**
     * @var boolean
     *
     * @ORM\Column(name="isPage", type="boolean")
     */
    private $isPage = false;

    /**
     * @var \DateTime
     *
     * @Gedmo\Timestampable(on="create")
     * @ORM\Column(name="createdAt", type="datetime") 
     */
    private $createdAt;

    /**
     * @var \DateTime
     *
     * @Gedmo\Timestampable(on="update")
     * @ORM\Column(name="updatedAt", type="datetime")
     */
    private $updatedAt;

    /**
     * @var User
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\User")
     * @ORM\JoinColumn(nullable=false)
     */
    private $author;

    /**
     * @var News[]|ArrayCollection
     * 
     * @ORM\ManyToMany(targetEntity="App\Entity\News", mappedBy="category")
     */
    private $news;

    /**
     * @var string
     *
     * @ORM\Column(name="cardFormat", type="string", length=255, nullable=true)
     */
    private $cardFormat = null;

    /**
     * @var string
     *
     * @ORM\Column(name="thumbnail", type="string", length=500, nullable=true)
     */
    private $thumbnail = null;

    public function __construct()
    {
        $this->parentcat = new ArrayCollection();
        $this->news = new ArrayCollection();
    }

    public function __toString()
    {
        return $this->getName();
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

    public function setTitleLandingPage($titleLandingPage)
    {
        $this->titleLandingPage = $titleLandingPage;

        return $this;
    }

    public function getTitleLandingPage()
    {
        return $this->titleLandingPage;
    }

    public function setParentcat(\App\Entity\NewsCategory $parent = null) {
        $this->parentcat = $parent;

        return $this;
    }

    public function getParentcat() {
        return $this->parentcat != null ? $this->parentcat : 'root';
    }

    public function getChildren() {
        return $this->children;
    }

    public function setUrl($url)
    {
        $this->url = $url;

        return $this;
    }

    public function getUrl()
    {
        return $this->url;
    }

    public function setUrlReplace($urlReplace)
    {
        $this->urlReplace = $urlReplace;

        return $this;
    }

    public function getUrlReplace()
    {
        return $this->urlReplace;
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

    public function setContent($content)
    {
        $this->content = $content;

        return $this;
    }

    public function getContent()
    {
        return $this->content;
    }

    public function setEnable($enable)
    {
        $this->enable = (bool) $enable;

        return $this;
    }

    public function getEnable()
    {
        return $this->enable;
    }

    public function setShowPostRelated($showPostRelated)
    {
        $this->showPostRelated = (bool) $showPostRelated;

        return $this;
    }

    public function getShowPostRelated()
    {
        return $this->showPostRelated;
    }

    public function setRobots($robots)
    {
        $this->robots = $robots;

        return $this;
    }

    public function getRobots()
    {
        return $this->robots;
    }

    public function setPageTitle($pageTitle)
    {
        $this->pageTitle = $pageTitle;

        return $this;
    }

    public function getPageTitle()
    {
        return $this->pageTitle;
    }

    public function setPageDescription($pageDescription)
    {
        $this->pageDescription = $pageDescription;

        return $this;
    }

    public function getPageDescription()
    {
        return $this->pageDescription;
    }

    public function setPageKeyword($pageKeyword)
    {
        $this->pageKeyword = $pageKeyword;

        return $this;
    }

    public function getPageKeyword()
    {
        return $this->pageKeyword;
    }

    public function setSortBy($sortBy)
    {
        $this->sortBy = $sortBy;

        return $this;
    }

    public function getSortBy()
    {
        return $this->sortBy;
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

    public function setAuthor(User $author)
    {
        $this->author = $author;
    }

    public function getAuthor()
    {
        return $this->author;
    }

    public function setIsPage($isPage)
    {
        $this->isPage = (bool) $isPage;

        return $this;
    }

    public function getIsPage()
    {
        return $this->isPage;
    }

    public function setSchemaMarkup($schemaMarkup)
    {
        $this->schemaMarkup = $schemaMarkup;

        return $this;
    }

    public function getSchemaMarkup()
    {
        return $this->schemaMarkup;
    }

    public function setCardFormat($cardFormat)
    {
        $this->cardFormat = $cardFormat;

        return $this;
    }

    public function getCardFormat()
    {
        return $this->cardFormat;
    }

    public function setThumbnail($thumbnail)
    {
        $thumbnail = is_string($thumbnail) ? trim($thumbnail) : $thumbnail;
        $this->thumbnail = $thumbnail ?: null;

        return $this;
    }

    public function getThumbnail()
    {
        return $this->thumbnail;
    }
}
