<?php

namespace App\Entity;

use App\Entity\News;
use App\Repository\CommentRepository;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

use Gedmo\Mapping\Annotation as Gedmo;
use EWZ\Bundle\RecaptchaBundle\Validator\Constraints as Recaptcha;

#[ORM\Entity(repositoryClass: CommentRepository::class)]
#[ORM\Table(name: 'comment', options: ['collate' => 'utf8_general_ci'])]
class Comment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private $id;

    #[ORM\ManyToOne(targetEntity: Comment::class)]
    #[ORM\JoinColumn(name: 'comment_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private $parent;

    #[ORM\ManyToOne(targetEntity: News::class)]
    #[ORM\JoinColumn(name: 'news_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotBlank(message: 'news.blank')]
    private $news;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'content.blank')]
    #[Assert\Length(
        min: 5,
        minMessage: 'content.too_short',
        max: 10000,
        maxMessage: 'content.too_long'
    )]
    private $content;

    #[ORM\Column(name: 'approved', type: Types::BOOLEAN)]
    private $approved = false;

    #[ORM\Column(name: 'email', type: Types::TEXT, nullable: true)]
    private $email;

    #[ORM\Column(name: 'phone', type: Types::TEXT, nullable: true)]
    private $phone;

    #[Assert\NotBlank]
    #[ORM\Column(name: 'author', type: Types::TEXT)]
    private $author;

    #[Assert\NotBlank]
    #[ORM\Column(name: 'ip', type: Types::TEXT)]
    private $ip;

    #[ORM\Column(name: 'gclid', type: Types::STRING, length: 255, nullable: true)]
    private $gclid;

    #[Gedmo\Timestampable(on: 'create')]
    #[ORM\Column(name: 'createdAt', type: Types::DATETIME_MUTABLE)]
    private $createdAt;

    #[Gedmo\Timestampable(on: 'update')]
    #[ORM\Column(name: 'updatedAt', type: Types::DATETIME_MUTABLE)]
    private $updatedAt;

    public $recaptcha;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    #[Assert\IsTrue(message: 'comment.is_spam')]
    public function isLegitComment()
    {
        $containsInvalidCharacters = false !== mb_strpos($this->content, '@');

        return !$containsInvalidCharacters;
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
     * @deprecated Không còn set trực tiếp được — dùng setNews(). Giữ lại no-op để code cũ
     * gọi setNewsId() không lỗi (chữ ký trước đây bind thẳng field int, giờ là quan hệ).
     */
    public function setNewsId($newsId)
    {
        return $this;
    }

    public function getNewsId()
    {
        return $this->news ? $this->news->getId() : null;
    }

    public function setNews(?News $news = null)
    {
        $this->news = $news;

        return $this;
    }

    /**
     * @deprecated Không còn set trực tiếp được — dùng setParent().
     */
    public function setCommentId($commentId)
    {
        return $this;
    }

    public function getCommentId()
    {
        return $this->parent ? $this->parent->getId() : null;
    }

    public function setParent(?Comment $parent = null)
    {
        $this->parent = $parent;

        return $this;
    }

    public function getParent()
    {
        return $this->parent;
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

    public function setApproved($approved)
    {
        $this->approved = $approved;

        return $this;
    }

    public function getApproved()
    {
        return $this->approved;
    }

    public function setEmail($email)
    {
        $this->email = $email;

        return $this;
    }

    public function getEmail()
    {
        return $this->email;
    }

    public function setPhone($phone)
    {
        $this->phone = $phone;

        return $this;
    }

    public function getPhone()
    {
        return $this->phone;
    }

    public function setAuthor($author)
    {
        $this->author = $author;

        return $this;
    }

    public function getAuthor()
    {
        return $this->author;
    }

    public function setIp($ip)
    {
        $this->ip = $ip;

        return $this;
    }

    public function getIp()
    {
        return $this->ip;
    }

    /**
     * Set createdAt
     *
     * @param \DateTime $createdAt
     * @return Comment
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
     * @return Comment
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
     * Set gclid
     *
     * @param string $gclid
     * @return Comment
     */
    public function setGclid($gclid)
    {
        $this->gclid = $gclid;

        return $this;
    }

    /**
     * Get gclid
     *
     * @return string
     */
    public function getGclid()
    {
        return $this->gclid;
    }

    /**
     * Get news
     *
     * @return News|null
     */
    public function getNews()
    {
        return $this->news;
    }
}
