<?php

namespace App\Entity;

use App\Repository\DailyStatsRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * DailyStats - Thống kê hàng ngày
 */
#[ORM\Table(name: 'daily_stats')]
#[ORM\UniqueConstraint(name: 'idx_daily_stats_date', columns: ['date'])]
#[ORM\Entity(repositoryClass: DailyStatsRepository::class)]
class DailyStats
{
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    private $id;

    #[ORM\Column(name: 'date', type: Types::DATE_MUTABLE)]
    private $date;

    #[ORM\Column(name: 'viewCount', type: Types::INTEGER, options: ['default' => 0])]
    private $viewCount = 0;

    #[Gedmo\Timestampable(on: 'create')]
    #[ORM\Column(name: 'createdAt', type: Types::DATETIME_MUTABLE)]
    private $createdAt;

    #[Gedmo\Timestampable(on: 'update')]
    #[ORM\Column(name: 'updatedAt', type: Types::DATETIME_MUTABLE)]
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
