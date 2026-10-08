<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * GscQueryStat - Số liệu Search Console theo ngày của từng cặp URL + truy vấn.
 *
 * Ghi bằng DBAL trong app:gsc:sync (xóa theo khoảng ngày rồi insert lại),
 * entity này chủ yếu để Doctrine quản lý schema.
 */
#[ORM\Table(name: 'gsc_query_stat')]
#[ORM\Index(name: 'idx_gsc_query_slug_date', columns: ['slug', 'date'])]
#[ORM\Index(name: 'idx_gsc_query_date', columns: ['date'])]
#[ORM\Index(name: 'idx_gsc_query_query', columns: ['query'], options: ['lengths' => [191]])]
#[ORM\Entity]
class GscQueryStat
{
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    private $id;

    #[ORM\Column(name: 'date', type: Types::DATE_MUTABLE)]
    private $date;

    // Đường dẫn không kèm domain, ví dụ /blog/sua-nha-tron-goi/
    #[ORM\Column(name: 'path', type: Types::STRING, length: 500)]
    private $path;

    // Đoạn cuối của path, khớp với News.url / NewsCategory.url; rỗng = trang chủ.
    #[ORM\Column(name: 'slug', type: Types::STRING, length: 191)]
    private $slug;

    #[ORM\Column(name: 'query', type: Types::STRING, length: 500)]
    private $query;

    #[ORM\Column(name: 'clicks', type: Types::INTEGER, options: ['default' => 0])]
    private $clicks = 0;

    #[ORM\Column(name: 'impressions', type: Types::INTEGER, options: ['default' => 0])]
    private $impressions = 0;

    // Vị trí trung bình trong ngày; khi gộp nhiều ngày phải tính trọng số theo impressions.
    #[ORM\Column(name: 'position', type: Types::FLOAT, options: ['default' => 0])]
    private $position = 0.0;

    public function getId()
    {
        return $this->id;
    }

    public function getDate()
    {
        return $this->date;
    }

    public function getPath()
    {
        return $this->path;
    }

    public function getSlug()
    {
        return $this->slug;
    }

    public function getQuery()
    {
        return $this->query;
    }

    public function getClicks()
    {
        return $this->clicks;
    }

    public function getImpressions()
    {
        return $this->impressions;
    }

    public function getPosition()
    {
        return $this->position;
    }
}
