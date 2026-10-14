<?php

namespace App\Seo\Gsc;

use Doctrine\DBAL\Connection;

/**
 * Đọc số liệu Search Console đã được app:gsc:sync lưu vào DB (bảng
 * gsc_page_stat, gsc_query_stat) cho một bài viết/trang, theo slug.
 *
 * Kỳ báo cáo kết thúc ở ngày mới nhất có trong DB chứ không phải hôm nay:
 * Search Console trễ 2–3 ngày, lấy "28 ngày tính từ hôm nay" sẽ luôn thiếu
 * mấy ngày cuối và làm số so sánh với kỳ trước bị lệch.
 */
final class GscStatsReader
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @return array{
     *     state: 'empty'|'no_rows'|'ok',
     *     days: int,
     *     from?: \DateTimeImmutable,
     *     to?: \DateTimeImmutable,
     *     totals?: array{clicks: int, impressions: int, ctr: float, position: float},
     *     previous?: array{clicks: int, impressions: int, ctr: float, position: float},
     *     series?: list<array{date: string, clicks: int, impressions: int}>,
     *     queries?: list<array{query: string, clicks: int, impressions: int, ctr: float, position: float}>
     * }
     */
    public function forSlug(string $slug, int $days = 28, int $queryLimit = 10): array
    {
        try {
            $latest = $this->connection->fetchOne('SELECT MAX(date) FROM gsc_page_stat');
        } catch (\Throwable) {
            // Bảng chưa được tạo (chưa chạy migration) — coi như chưa có dữ liệu.
            return ['state' => 'empty', 'days' => $days];
        }

        if (!$latest) {
            return ['state' => 'empty', 'days' => $days];
        }

        $to = new \DateTimeImmutable((string) $latest);
        $from = $to->modify('-' . ($days - 1) . ' days');
        $previousTo = $from->modify('-1 day');
        $previousFrom = $previousTo->modify('-' . ($days - 1) . ' days');

        $totals = $this->totals($slug, $from, $to);
        $result = ['state' => 'no_rows', 'days' => $days, 'from' => $from, 'to' => $to];

        if ($totals['impressions'] === 0 && $totals['clicks'] === 0) {
            return $result;
        }

        return [
            'state' => 'ok',
            'totals' => $totals,
            'previous' => $this->totals($slug, $previousFrom, $previousTo),
            'series' => $this->series($slug, $from, $to),
            'queries' => $this->queries($slug, $from, $to, $queryLimit),
        ] + $result;
    }

    /**
     * @return array{clicks: int, impressions: int, ctr: float, position: float}
     */
    private function totals(string $slug, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        // Vị trí trung bình của cả kỳ phải tính trọng số theo impressions.
        $row = $this->connection->fetchAssociative(
            'SELECT COALESCE(SUM(clicks), 0) AS clicks, COALESCE(SUM(impressions), 0) AS impressions,
                    COALESCE(SUM(position * impressions), 0) AS weighted
               FROM gsc_page_stat
              WHERE slug = :slug AND date BETWEEN :from AND :to',
            ['slug' => $slug, 'from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')]
        ) ?: [];

        return self::shape((int) ($row['clicks'] ?? 0), (int) ($row['impressions'] ?? 0), (float) ($row['weighted'] ?? 0));
    }

    /**
     * Một điểm cho mỗi ngày trong kỳ, ngày không có dữ liệu là 0.
     *
     * @return list<array{date: string, clicks: int, impressions: int}>
     */
    private function series(string $slug, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT date, SUM(clicks) AS clicks, SUM(impressions) AS impressions
               FROM gsc_page_stat
              WHERE slug = :slug AND date BETWEEN :from AND :to
              GROUP BY date',
            ['slug' => $slug, 'from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')]
        );

        $byDate = [];

        foreach ($rows as $row) {
            $byDate[substr((string) $row['date'], 0, 10)] = $row;
        }

        $series = [];

        for ($day = $from; $day <= $to; $day = $day->modify('+1 day')) {
            $key = $day->format('Y-m-d');
            $series[] = [
                'date' => $key,
                'clicks' => (int) ($byDate[$key]['clicks'] ?? 0),
                'impressions' => (int) ($byDate[$key]['impressions'] ?? 0),
            ];
        }

        return $series;
    }

    /**
     * @return list<array{query: string, clicks: int, impressions: int, ctr: float, position: float}>
     */
    private function queries(string $slug, \DateTimeImmutable $from, \DateTimeImmutable $to, int $limit): array
    {
        try {
            $rows = $this->connection->fetchAllAssociative(
                'SELECT query, SUM(clicks) AS clicks, SUM(impressions) AS impressions,
                        SUM(position * impressions) AS weighted
                   FROM gsc_query_stat
                  WHERE slug = :slug AND date BETWEEN :from AND :to
                  GROUP BY query
                  ORDER BY clicks DESC, impressions DESC
                  LIMIT ' . max(1, $limit),
                ['slug' => $slug, 'from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')]
            );
        } catch (\Throwable) {
            return [];
        }

        return array_map(
            static fn (array $row): array => ['query' => (string) $row['query']]
                + self::shape((int) $row['clicks'], (int) $row['impressions'], (float) $row['weighted']),
            $rows
        );
    }

    /**
     * @return array{clicks: int, impressions: int, ctr: float, position: float}
     */
    private static function shape(int $clicks, int $impressions, float $weightedPosition): array
    {
        return [
            'clicks' => $clicks,
            'impressions' => $impressions,
            'ctr' => $impressions > 0 ? $clicks / $impressions * 100 : 0.0,
            'position' => $impressions > 0 ? $weightedPosition / $impressions : 0.0,
        ];
    }
}
