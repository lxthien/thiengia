<?php

namespace App\Entity;

use App\Entity\News;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

use Gedmo\Mapping\Annotation as Gedmo;
use EWZ\Bundle\RecaptchaBundle\Validator\Constraints as Recaptcha;

/**
 * @ORM\Entity(repositoryClass="App\Repository\CommentRepository")
 * @ORM\Table(name="comment", options={"collate"="utf8_general_ci"})
 */

class Comment
{
    /**
     * @var int
     *
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @var int
     *
     * @ORM\Column(name="comment_id", type="integer", nullable=true)
     */
    private $comment_id;

    /**
     * @var int
     *
     * @ORM\Column(name="news_id", type="integer", nullable=false)
     * @Assert\NotBlank(message="news.blank")
     */
    private $news_id;

    /**
     * @var string
     *
     * @ORM\Column(type="text")
     * @Assert\NotBlank(message="content.blank")
     * @Assert\Length(
     *     min=5,
     *     minMessage="content.too_short",
     *     max=10000,
     *     maxMessage="content.too_long"
     * )
     */
    private $content;

    /**
     * @var boolean
     *
     * @ORM\Column(name="approved", type="boolean")
     */
    private $approved = false;

    /**
     * @var string
     *
     * @ORM\Column(name="email", type="text", nullable=true)
     */
    private $email;

    /**
     * @var string
     *
     * @ORM\Column(name="phone", type="text", nullable=true)
     */
    private $phone;

    /**
     * @var string
     *
     * @Assert\NotBlank()
     * @ORM\Column(name="author", type="text")
     */
    private $author;

    /**
     * @var string
     *
     * @Assert\NotBlank()
     * @ORM\Column(name="ip", type="text")
     */
    private $ip;

    /**
     * @var string
     *
     * @ORM\Column(name="gclid", type="string", length=255, nullable=true)
     */
    private $gclid;

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

    public $recaptcha;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    /**
     * @Assert\IsTrue(message="comment.is_spam")
     */
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
     * Set news_id
     *
     * @param int $newsId
     * @return Comment
     */
    public function setNewsId($newsId)
    {
        $this->news_id = $newsId;

        return $this;
    }

    public function getNewsId()
    {
        return $this->news_id;
    }

    public function setCommentId($commentId)
    {
        $this->comment_id = $commentId;

        return $this;
    }

    public function getCommentId()
    {
        return $this->comment_id;
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
     * @return App\Entity\News
     */
    public function getNews()
    {
        global $kernel;
        if ('AppCache' === get_class($kernel)) {
            $kernel = $kernel->getKernel();
        }
        $em = $kernel->getContainer()->get('doctrine')->getManager();
        
        return $em->getRepository(\App\Entity\News::class)
            ->findOneBy(
                array('id'=> $this->getNewsId())
            );
    }
}
