<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * DailyStats - Thống kê hàng ngày
 *
 * @ORM\Table(name="daily_stats", uniqueConstraints={
 *     @ORM\UniqueConstraint(name="idx_daily_stats_date", columns={"date"})
 * })
 * @ORM\Entity(repositoryClass="App\Repository\DailyStatsRepository")
 */
class DailyStats
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
     * @var \DateTime
     *
     * @ORM\Column(name="date", type="date")
     */
    private $date;

    /**
     * @var int
     *
     * @ORM\Column(name="viewCount", type="integer", options={"default": 0})
     */
    private $viewCount = 0;

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

    public function __construct(\DateTime $date = null)
    {
        $this->date = $date ?: new \DateTime();
    }

    public function getId()
    {
        return $this->id;
    }

    public function getDate()
    {
        return $this->date;
    }

    public function setDate(\DateTime $date)
    {
        $this->date = $date;
        return $this;
    }

    public function getViewCount()
    {
        return $this->viewCount;
    }

    public function setViewCount($viewCount)
    {
        $this->viewCount = $viewCount;
        return $this;
    }

    public function incrementViewCount($amount = 1)
    {
        $this->viewCount += $amount;
        return $this;
    }

    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }
}
