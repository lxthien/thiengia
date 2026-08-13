<?php

namespace App\Entity;

use App\Repository\NewsCategoryRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\HttpFoundation\File\File;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * NewsCategory
 */
#[ORM\Table(name: 'newscategory', options: ['collate' => 'utf8_general_ci'])]
// Routing công khai tra cứu danh mục theo url+enable nhiều lần mỗi request
// (handleSingleSegment/handleTwoSegments/handleThreeSegments/listAction) mà
// trước đây bảng này không có index nào ngoài khóa chính/khóa ngoại.
#[ORM\Index(columns: ['url', 'enable'], name: 'idx_newscategory_url_enable')]
#[ORM\Entity(repositoryClass: NewsCategoryRepository::class)]
class NewsCategory
{
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    private $id;

    /**
     * One Category has Many Categories.
     */
    #[ORM\OneToMany(targetEntity: NewsCategory::class, mappedBy: 'parentcat')]
    protected $children;

    /**
     * Many Categories have One Category.
     */
    #[ORM\ManyToOne(targetEntity: NewsCategory::class, inversedBy: 'children')]
    #[ORM\JoinColumn(name: 'parentcat_id', referencedColumnName: 'id', nullable: true)]
    private $parentcat;

    #[ORM\Column(name: 'name', type: Types::STRING, length: 255)]
    private $name;

    #[ORM\Column(name: 'titleLandingPage', type: Types::STRING, length: 255, nullable: true)]
    private $titleLandingPage;

    #[ORM\Column(name: 'url', type: Types::STRING, length: 255)]
    private $url;

    #[ORM\Column(name: 'urlReplace', type: Types::STRING, length: 255, nullable: true)]
    private $urlReplace;

    #[ORM\Column(name: 'description', type: Types::TEXT, nullable: true)]
    private $description = null;

    #[ORM\Column(name: 'content', type: Types::TEXT, nullable: true)]
    private $content = null;

    #[ORM\Column(name: 'enable', type: Types::BOOLEAN)]
    private $enable = true;

    #[ORM\Column(name: 'showPostRelated', type: Types::BOOLEAN)]
    private $showPostRelated = false;

    // Thay cho field "robots" dạng text tự do — xem comment cùng tên trong News.php.
    #[ORM\Column(name: 'metaIndex', type: Types::BOOLEAN, options: ['default' => true])]
    private $metaIndex = true;

    #[ORM\Column(name: 'metaFollow', type: Types::BOOLEAN, options: ['default' => true])]
    private $metaFollow = true;

    #[ORM\Column(name: 'pageTitle', type: Types::STRING, length: 255, nullable: true)]
    private $pageTitle = null;

    #[ORM\Column(name: 'pageDescription', type: Types::TEXT, nullable: true)]
    private $pageDescription = null;

    #[ORM\Column(name: 'pageKeyword', type: Types::STRING, length: 255, nullable: true)]
    private $pageKeyword = null;

    #[ORM\Column(name: 'sortBy', type: Types::STRING, length: 255, nullable: true)]
    private $sortBy = null;

    #[ORM\Column(name: 'schemaMarkup', type: Types::TEXT, nullable: true)]
    private $schemaMarkup = null;

    #[ORM\Column(name: 'isPage', type: Types::BOOLEAN)]
    private $isPage = false;

    #[Gedmo\Timestampable(on: 'create')]
    #[ORM\Column(name: 'createdAt', type: Types::DATETIME_MUTABLE)]
    private $createdAt;

    #[Gedmo\Timestampable(on: 'update')]
    #[ORM\Column(name: 'updatedAt', type: Types::DATETIME_MUTABLE)]
    private $updatedAt;

    /**
     * @var User
     */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private $author;

    /**
     * @var News[]|ArrayCollection
     */
    #[ORM\ManyToMany(targetEntity: News::class, mappedBy: 'category')]
    private $news;

    #[ORM\Column(name: 'cardFormat', type: Types::STRING, length: 255, nullable: true)]
    private $cardFormat = null;

    #[ORM\Column(name: 'thumbnail', type: Types::STRING, length: 500, nullable: true)]
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

    public function setMetaIndex($metaIndex)
    {
        $this->metaIndex = (bool) $metaIndex;

        return $this;
    }

    public function isMetaIndex()
    {
        return (bool) $this->metaIndex;
    }

    public function setMetaFollow($metaFollow)
    {
        $this->metaFollow = (bool) $metaFollow;

        return $this;
    }

    public function isMetaFollow()
    {
        return (bool) $this->metaFollow;
    }

    /**
     * Nội dung thật cho thẻ <meta name="robots">, vd "index, follow".
     */
    public function getRobotsContent(): string
    {
        return ($this->metaIndex ? 'index' : 'noindex') . ', ' . ($this->metaFollow ? 'follow' : 'nofollow');
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
