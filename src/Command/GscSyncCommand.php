<?php

namespace App\Command;

use App\Seo\Gsc\SearchConsoleClient;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Lấy số liệu Search Console (clicks, impressions, vị trí) theo ngày của từng
 * URL và từng cặp URL + truy vấn, lưu vào gsc_page_stat / gsc_query_stat.
 * Google chỉ giữ 16 tháng; lưu về DB để giữ được lâu hơn.
 *
 * Số liệu "final" của Google trễ 2–3 ngày, nên mặc định mỗi lần chạy sẽ lấy
 * lại 7 ngày gần nhất (ghi đè) để bù các ngày lần trước chưa có dữ liệu.
 *
 * Chạy qua cron mỗi ngày một lần:
 *   30 5 * * * cd /path/to/project && php bin/console app:gsc:sync >> var/log/gsc-sync.log 2>&1
 *
 * Lấy bù dữ liệu cũ (tối đa 16 tháng) lần đầu:
 *   php bin/console app:gsc:sync --from=2025-06-01
 */
class GscSyncCommand extends Command
{
    // Mỗi lượt gọi API + ghi DB gói gọn trong 7 ngày để không giữ quá nhiều dòng trong bộ nhớ.
    private const CHUNK_DAYS = 7;
    private const INSERT_BATCH = 500;

    public function __construct(
        private readonly SearchConsoleClient $client,
        private readonly Connection $connection,
    ) {
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('app:gsc:sync')
            ->setDescription('Đồng bộ số liệu Google Search Console vào database.')
            ->addOption('days', null, InputOption::VALUE_REQUIRED, 'Số ngày gần nhất cần lấy lại', '7')
            ->addOption('from', null, InputOption::VALUE_REQUIRED, 'Ngày bắt đầu (YYYY-MM-DD), dùng khi lấy bù dữ liệu cũ')
            ->addOption('to', null, InputOption::VALUE_REQUIRED, 'Ngày kết thúc (YYYY-MM-DD), mặc định là hôm qua');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!$this->client->isConfigured()) {
            $output->writeln('Chua cau hinh Search Console (GSC_SITE_URL / GSC_CREDENTIALS_PATH), bo qua.');

            return Command::SUCCESS;
        }

        try {
            $to = $this->parseDate($input->getOption('to')) ?? new \DateTimeImmutable('yesterday');
            $from = $this->parseDate($input->getOption('from'))
                ?? $to->modify(sprintf('-%d days', max(1, (int) $input->getOption('days')) - 1));
        } catch (\InvalidArgumentException $e) {
            $output->writeln($e->getMessage());

            return Command::FAILURE;
        }

        if ($from > $to) {
            $output->writeln('Ngay bat dau phai truoc ngay ket thuc.');

            return Command::FAILURE;
        }

        $totalPages = 0;
        $totalQueries = 0;

        try {
            for ($start = $from; $start <= $to; $start = $start->modify('+' . self::CHUNK_DAYS . ' days')) {
                $end = min($to, $start->modify('+' . (self::CHUNK_DAYS - 1) . ' days'));

                $pages = $this->fetch($start, $end, false);
                $queries = $this->fetch($start, $end, true);

                $this->connection->beginTransaction();
                try {
                    $this->replace('gsc_page_stat', $start, $end, $pages);
                    $this->replace('gsc_query_stat', $start, $end, $queries);
                    $this->connection->commit();
                } catch (\Throwable $e) {
                    $this->connection->rollBack();
                    throw $e;
                }

                $totalPages += count($pages);
                $totalQueries += count($queries);
                $output->writeln(sprintf('%s -> %s: %d dong URL, %d dong truy van.', $start->format('Y-m-d'), $end->format('Y-m-d'), count($pages), count($queries)));
            }
        } catch (\Throwable $e) {
            $output->writeln('Loi khi dong bo Search Console: ' . $e->getMessage());

            return Command::FAILURE;
        }

        $output->writeln(sprintf('Da dong bo %s (%s -> %s): %d dong URL, %d dong truy van.', $this->client->getSiteUrl(), $from->format('Y-m-d'), $to->format('Y-m-d'), $totalPages, $totalQueries));

        return Command::SUCCESS;
    }

    /**
     * Gộp các dòng API về cùng (ngày, path[, truy vấn]): property dạng
     * sc-domain trả riêng http/https, www/không www, và URL kèm query string.
     *
     * @return array<int, array<string, mixed>>
     */
    private function fetch(\DateTimeImmutable $from, \DateTimeImmutable $to, bool $withQuery): array
    {
        $rows = [];
        $dimensions = $withQuery ? ['date', 'page', 'query'] : ['date', 'page'];

        foreach ($this->client->searchAnalytics($from, $to, $dimensions) as $row) {
            $path = self::normalizePath($row['keys'][1]);
            $query = $withQuery ? mb_substr($row['keys'][2], 0, 500) : null;
            $key = $row['keys'][0] . "\n" . $path . "\n" . $query;
            $impressions = (int) $row['impressions'];

            if (!isset($rows[$key])) {
                $rows[$key] = [
                    'date' => $row['keys'][0],
                    'path' => $path,
                    'slug' => self::slugOf($path),
                    'clicks' => 0,
                    'impressions' => 0,
                    'position' => 0.0,
                ];
                if ($withQuery) {
                    $rows[$key]['query'] = $query;
                }
            }

            // Vị trí trung bình phải lấy trọng số theo impressions khi gộp.
            $merged = $rows[$key]['impressions'] + $impressions;
            if ($merged > 0) {
                $rows[$key]['position'] = ($rows[$key]['position'] * $rows[$key]['impressions'] + (float) $row['position'] * $impressions) / $merged;
            }
            $rows[$key]['impressions'] = $merged;
            $rows[$key]['clicks'] += (int) $row['clicks'];
        }

        return array_values($rows);
    }

    private function replace(string $table, \DateTimeImmutable $from, \DateTimeImmutable $to, array $rows): void
    {
        $this->connection->executeStatement(
            sprintf('DELETE FROM %s WHERE date BETWEEN ? AND ?', $table),
            [$from->format('Y-m-d'), $to->format('Y-m-d')]
        );

        foreach (array_chunk($rows, self::INSERT_BATCH) as $batch) {
            $columns = array_keys($batch[0]);
            $placeholder = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';
            $params = [];
            foreach ($batch as $row) {
                array_push($params, ...array_values($row));
            }

            $this->connection->executeStatement(
                sprintf('INSERT INTO %s (%s) VALUES %s', $table, implode(', ', $columns), implode(', ', array_fill(0, count($batch), $placeholder))),
                $params
            );
        }
    }

    private function parseDate(?string $value): ?\DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw new \InvalidArgumentException(sprintf('Ngay khong hop le: "%s" (can dang YYYY-MM-DD).', $value));
        }

        return $date;
    }

    private static function normalizePath(string $url): string
    {
        $path = rawurldecode((string) (parse_url($url, PHP_URL_PATH) ?: '/'));

        return mb_substr($path, 0, 500);
    }

    private static function slugOf(string $path): string
    {
        $segments = array_values(array_filter(explode('/', $path), 'strlen'));

        return mb_substr((string) end($segments), 0, 191);
    }
}
