<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

use Gedmo\Mapping\Annotation as Gedmo;

/**
 * @ORM\Entity(repositoryClass="App\Repository\RatingRepository")
 * @ORM\Table(name="rating")
 */

class Rating
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
     * @ORM\Column(name="news_id", type="integer", nullable=false)
     * @Assert\NotBlank(message="news.blank")
     */
    private $news_id;

    /**
     * @var int
     *
     * @ORM\Column(name="rating", type="integer", nullable=false)
     * @Assert\NotBlank(message="rating.blank")
     */
    private $rating;

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

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    /**
     * Get rating ID
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set news ID
     *
     * @param int $newsId
     * @return Rating
     */
    public function setNewsId($newsId)
    {
        $this->news_id = $newsId;

        return $this;
    }

    /**
     * Get news ID
     *
     * @return int
     */
    public function getNewsId()
    {
        return $this->news_id;
    }

    /**
     * Set rating
     *
     * @param int $rating
     * @return Rating
     */
    public function setRating($rating)
    {
        $this->rating = $rating;

        return $this;
    }

    /**
     * Get rating
     *
     * @return int
     */
    public function getRating()
    {
        return $this->rating;
    }

    /**
     * Set createdAt
     *
     * @param \DateTime $createdAt
     * @return Rating
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
     * @return Rating
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
