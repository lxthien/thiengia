<?php

namespace App\Entity;

use App\Enum\PostStatus;
use App\Repository\NewsRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Validator\Constraints as Assert;

use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

use Gedmo\Mapping\Annotation as Gedmo;

/**
 * News
 */
#[ORM\Table(name: 'news', options: ['charset' => 'utf8mb4', 'collate' => 'utf8mb4_unicode_ci'])]
// Hầu hết query công khai lọc theo cả postType (post/page) lẫn status
// (published) cùng lúc — xem NewsRepository, SitemapService, HealthAuditManager.
#[ORM\Index(columns: ['postType', 'status'], name: 'idx_news_posttype_status')]
#[ORM\Entity(repositoryClass: NewsRepository::class)]
#[UniqueEntity('url')]
class News
{
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    private $id;

    /**
     * @var NewsCategory
     */
    #[ORM\ManyToMany(targetEntity: NewsCategory::class, inversedBy: 'news')]
    #[ORM\JoinTable(name: 'news_newscategory')]
    #[ORM\JoinColumn(name: 'news_id', referencedColumnName: 'id')]
    #[ORM\InverseJoinColumn(name: 'newscategory_id', referencedColumnName: 'id')]
    private $category;

    #[Assert\NotBlank]
    #[Assert\Length(
        min: 10,
        max: 255,
        minMessage: 'Your title must be at least {{ limit }} characters long',
        maxMessage: 'Your title cannot be longer than {{ limit }} characters'
    )]
    #[ORM\Column(name: 'title', type: Types::STRING, length: 255)]
    private $title;

    #[Assert\NotBlank]
    #[ORM\Column(name: 'url', type: Types::STRING, length: 255, unique: true)]
    private $url;

    #[Assert\NotBlank]
    #[ORM\Column(name: 'description', type: Types::TEXT)]
    private $description;

    /**
     * @var text
     */
    #[Assert\NotBlank]
    #[ORM\Column(name: 'contents', type: Types::TEXT, columnDefinition: 'LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL')]
    private $contents;

    /**
     * URL đầy đủ tính từ webroot (vd "/uploads/media/2026/08/xxx.jpg"), chọn
     * qua Media picker — cùng kiểu dữ liệu Banner::$urlImage/Testimonial::$avatarUrl
     * đang dùng, không còn qua VichUploaderBundle.
     */
    #[ORM\Column(name: 'images', type: Types::STRING, length: 255, nullable: true)]
    private $images;

    #[ORM\Column(name: 'status', type: Types::STRING, length: 20, enumType: PostStatus::class)]
    private PostStatus $status = PostStatus::Draft;

    #[ORM\Column(name: 'publishedAt', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $publishedAt = null;

    #[ORM\Column(name: 'scheduledAt', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $scheduledAt = null;

    #[ORM\Column(name: 'postType', type: Types::STRING, length: 255)]
    private $postType = 'post';

    #[ORM\Column(name: 'pageTitle', type: Types::STRING, length: 255, nullable: true)]
    private $pageTitle = null;

    #[ORM\Column(name: 'pageDescription', type: Types::TEXT, nullable: true)]
    private $pageDescription = null;

    #[ORM\Column(name: 'pageKeyword', type: Types::STRING, length: 255, nullable: true)]
    private $pageKeyword = null;

    #[ORM\Column(name: 'breadcrumbTitle', type: Types::STRING, length: 255, nullable: true)]
    private $breadcrumbTitle = null;

    #[ORM\Column(name: 'relatedNews', type: Types::STRING, length: 255, nullable: true)]
    private $relatedNews = null;

    #[ORM\Column(name: 'contactHotline', type: Types::TEXT, nullable: true)]
    private $contactHotline = null;

    #[ORM\Column(name: 'viewCounts', type: Types::INTEGER)]
    private $viewCounts = 0;

    #[ORM\Column(name: 'ordering', type: Types::INTEGER, nullable: true)]
    private $ordering = null;

    #[ORM\Column(name: 'categoryPrimary', type: Types::INTEGER)]
    private $categoryPrimary = 0;

    #[ORM\Column(name: 'schemaMarkup', type: Types::TEXT, nullable: true)]
    private $schemaMarkup = null;

    #[ORM\Column(name: 'note', type: Types::TEXT, nullable: true)]
    private $note = null;

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
     * @var News
     */
    #[ORM\ManyToOne(targetEntity: News::class, inversedBy: 'children')]
    #[ORM\JoinColumn(name: 'parent_id', referencedColumnName: 'id', nullable: true)]
    private $parent;

    /**
     * @var News[]|ArrayCollection
     */
    #[ORM\OneToMany(targetEntity: News::class, mappedBy: 'parent')]
    #[ORM\OrderBy(['title' => 'ASC'])]
    private $children;

    /**
     * @var Tag[]|ArrayCollection
     */
    #[ORM\ManyToMany(targetEntity: Tag::class, inversedBy: 'news', cascade: ['persist'])]
    #[ORM\OrderBy(['name' => 'ASC'])]
    #[Assert\Count(max: 10, maxMessage: 'news.too_many_tags')]
    private $tags;

    // Thay cho field "robots" dạng text tự do trước đây (vd "noindex,nofollow")
    // — admin dễ gõ sai/không biết cú pháp, và không thể kiểm tra được true/false
    // ở code mà phải strpos() chuỗi. 2 checkbox độc lập, mặc định bật cả 2 (=
    // index, follow, giống hành vi mặc định khi không set gì).
    #[ORM\Column(name: 'metaIndex', type: Types::BOOLEAN, options: ['default' => true])]
    private $metaIndex = true;

    #[ORM\Column(name: 'metaFollow', type: Types::BOOLEAN, options: ['default' => true])]
    private $metaFollow = true;

    #[ORM\Column(name: 'template', type: Types::STRING, length: 255, nullable: true)]
    private $template = null;

    #[ORM\Column(name: 'pageBuilderEnabled', type: Types::BOOLEAN, options: ['default' => 0])]
    private $pageBuilderEnabled = false;

    #[ORM\Column(name: 'pageBuilderData', type: Types::TEXT, nullable: true, columnDefinition: 'LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL')]
    private $pageBuilderData = null;

    public function __toString()
    {
        return (string)$this->getTitle();
    }

    public function __construct()
    {
        $this->category = new ArrayCollection();
        $this->tags = new ArrayCollection();
        $this->comments = new ArrayCollection();
        $this->children = new ArrayCollection();
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
     * Add tag for the news
     *
     * @return Tag[]
     */
    public function addTag(Tag $tag)
    {
        if (!$this->tags->contains($tag)) {
            $this->tags->add($tag);
        }
    }

    /**
     * Remove tag for the news
     *
     * @return Tag[]
     */
    public function removeTag(Tag $tag)
    {
        $this->tags->removeElement($tag);
    }

    /**
     * Get the list of tag associated to the news.
     *
     * @return \App\Entity\Tag
     */
    public function getTags()
    {
        return $this->tags;
    }

    /**
     * Set name
     *
     * @param string $title
     *
     * @return News
     */
    public function setTitle($title)
    {
        $this->title = $title;

        return $this;
    }

    /**
     * Get name
     *
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * Set category
     *
     * @param \App\Entity\NewsCategory $category
     * @return NewsCategory
     */
    public function setCategory(\App\Entity\NewsCategory $category = null)
    {
        $this->category = $category;

        return $this;
    }

    /**
     * @return NewsCategory[]
     */
    public function addCategory(NewsCategory $newsCategory)
    {
        if (!$this->category->contains($newsCategory)) {
            $this->category->add($newsCategory);
        }
    }

    /**
     * @return NewsCategory[]
     */
    public function removeCategory(NewsCategory $newsCategory)
    {
        $this->category->removeElement($newsCategory);
    }

    /**
     * @return \App\Entity\NewsCategory
     */
    public function getCategory()
    {
        return $this->category;
    }

    /**
     * Set url
     *
     * @param string $url
     *
     * @return News
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
     * Set description
     *
     * @param string $description
     * @return News
     */
    public function setDescription($description)
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Get description
     *
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * Set contents
     *
     * @param string $contents
     * @return News
     */
    public function setContents($contents)
    {
        $this->contents = $contents;

        return $this;
    }

    /**
     * Get contents
     *
     * @return string
     */
    public function getContents()
    {
        return $this->contents;
    }

    /**
     * Set images
     *
     * @param string $images
     * @return News
     */
    public function setImages($images)
    {
        $this->images = $images;

        return $this;
    }

    public function getImages()
    {
        return $this->images;
    }

    public function getStatus(): PostStatus
    {
        return $this->status;
    }

    /**
     * Tự đánh dấu publishedAt lần đầu tiên chuyển sang Published — không ghi
     * đè nếu đã xuất bản trước đó rồi lại chuyển trạng thái qua lại.
     */
    public function setStatus(PostStatus $status): static
    {
        $this->status = $status;

        if ($status === PostStatus::Published && !$this->publishedAt) {
            $this->publishedAt = new \DateTime();
        }

        return $this;
    }

    public function isPublished(): bool
    {
        return $this->status === PostStatus::Published;
    }

    public function isDraft(): bool
    {
        return $this->status === PostStatus::Draft;
    }

    public function isScheduled(): bool
    {
        return $this->status === PostStatus::Scheduled;
    }

    public function getPublishedAt(): ?\DateTimeInterface
    {
        return $this->publishedAt;
    }

    public function setPublishedAt(?\DateTimeInterface $publishedAt): static
    {
        $this->publishedAt = $publishedAt;

        return $this;
    }

    public function getScheduledAt(): ?\DateTimeInterface
    {
        return $this->scheduledAt;
    }

    public function setScheduledAt(?\DateTimeInterface $scheduledAt): static
    {
        $this->scheduledAt = $scheduledAt;

        return $this;
    }

    public function setPostType($postType)
    {
        $this->postType = $postType;

        return $this;
    }

    public function getPostType()
    {
        return $this->postType;
    }

    public function isPage()
    {
        return ($this->postType == 'page') ? true : false;
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

    public function setBreadcrumbTitle($breadcrumbTitle)
    {
        $this->breadcrumbTitle = $breadcrumbTitle;

        return $this;
    }

    public function getBreadcrumbTitle()
    {
        return $this->breadcrumbTitle;
    }

    public function setRelatedNews($relatedNews)
    {
        $this->relatedNews = $relatedNews;

        return $this;
    }

    public function getRelatedNews()
    {
        return $this->relatedNews;
    }

    public function setContactHotline($contactHotline)
    {
        $this->contactHotline = $contactHotline;

        return $this;
    }

    public function getContactHotline()
    {
        return $this->contactHotline;
    }

    public function setViewCounts($viewCounts)
    {
        $this->viewCounts = $viewCounts;

        return $this;
    }

    public function getViewCounts()
    {
        return $this->viewCounts;
    }

    public function setOrdering($ordering)
    {
        $this->ordering = $ordering;

        return $this;
    }

    public function getOrdering()
    {
        return $this->ordering;
    }

    public function setCategoryPrimary($categoryPrimary)
    {
        $this->categoryPrimary = $categoryPrimary;

        return $this;
    }

    public function getCategoryPrimary()
    {
        return $this->categoryPrimary;
    }

    /**
     * Resolve a single "primary" category for display contexts that only
     * show one category per post (cards, meta tags...). $category is a
     * ManyToMany collection, so this picks the entry matching
     * categoryPrimary, falling back to the first assigned category.
     */
    public function getPrimaryCategory(): ?NewsCategory
    {
        if ($this->categoryPrimary) {
            foreach ($this->category as $cat) {
                if ($cat->getId() === $this->categoryPrimary) {
                    return $cat;
                }
            }
        }

        return $this->category->first() ?: null;
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

    public function getAuthor()
    {
        return $this->author;
    }

    public function setAuthor(User $author)
    {
        $this->author = $author;
    }

    public function getParent()
    {
        return $this->parent;
    }

    public function setParent(News $parent = null)
    {
        $this->parent = $parent;
    }

    public function getChildren()
    {
        return $this->children;
    }

    public function addChild(News $child)
    {
        if (!$this->children->contains($child)) {
            $this->children->add($child);
            $child->setParent($this);
        }
    }

    public function removeChild(News $child)
    {
        if ($this->children->contains($child)) {
            $this->children->removeElement($child);
            $child->setParent(null);
        }
    }

    public function getComments()
    {
        return $this->comments;
    }

    public function addComment(Comment $comment)
    {
        $comment->setNews($this);
        if (!$this->comments->contains($comment)) {
            $this->comments->add($comment);
        }
    }

    public function removeComment(Comment $comment)
    {
        $comment->setNews(null);
        $this->comments->removeElement($comment);
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

    public function setSchemaMarkup($schemaMarkup)
    {
        $this->schemaMarkup = $schemaMarkup;

        return $this;
    }

    public function getSchemaMarkup()
    {
        return $this->schemaMarkup;
    }

    public function getNote()
    {
        return $this->note;
    }

    public function setNote($note)
    {
        $this->note = $note;
        return $this;
    }

    public function setTemplate($template)
    {
        $this->template = $template;

        return $this;
    }

    public function getTemplate()
    {
        return $this->template;
    }

    public function setPageBuilderEnabled($pageBuilderEnabled)
    {
        $this->pageBuilderEnabled = (bool) $pageBuilderEnabled;

        return $this;
    }

    public function getPageBuilderEnabled()
    {
        return $this->pageBuilderEnabled;
    }

    public function isPageBuilderEnabled()
    {
        return (bool) $this->pageBuilderEnabled;
    }

    public function setPageBuilderData($pageBuilderData)
    {
        $this->pageBuilderData = $pageBuilderData;

        return $this;
    }

    public function getPageBuilderData()
    {
        return $this->pageBuilderData;
    }
}
