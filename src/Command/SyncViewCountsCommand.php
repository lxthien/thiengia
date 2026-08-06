<?php

namespace App\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SyncViewCountsCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('app:sync-views')
            ->setDescription('Đồng bộ lượt xem bài viết từ file log vào mảng database để khắc phục deadlock.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $logDir = $this->getContainer()->getParameter('kernel.logs_dir');
        $logFile = rtrim($logDir, '/\\') . '/view_counts.log';
        $processingFile = $logFile . '.processing';

        if (!file_exists($logFile) || filesize($logFile) === 0) {
            $output->writeln("Khong co log view nao can xu ly.");
            return;
        }

        // Đổi tên file để lock tạm thời, tránh việc file vẫn bị ghi vào trong lúc đọc
        rename($logFile, $processingFile);

        // Đọc nội dung file log
        $contents = file_get_contents($processingFile);
        $lines = explode(PHP_EOL, trim($contents));
        
        // Đếm tuần suất ID nào được xem bao nhiêu lần
        $viewCounts = array();
        foreach ($lines as $line) {
            $postId = (int) trim($line);
            if ($postId > 0) {
                if (!isset($viewCounts[$postId])) {
                    $viewCounts[$postId] = 0;
                }
                $viewCounts[$postId]++;
            }
        }

        if (count($viewCounts) > 0) {
            $em = $this->getContainer()->get('doctrine')->getManager();
            $connection = $em->getConnection();
            $connection->beginTransaction();

            try {
                // Prepare statement cập nhật dồn cực kỳ tối ưu
                $statement = $connection->prepare('UPDATE news SET viewCounts = viewCounts + ? WHERE id = ?');

                foreach ($viewCounts as $postId => $count) {
                    $statement->execute([$count, $postId]);
                }

                // Update DailyStats
                $today = new \DateTime();
                $today->setTime(0, 0, 0);
                
                $dailyStatsRepo = $em->getRepository(\App\Entity\DailyStats::class);
                $dailyStats = $dailyStatsRepo->findOneBy(['date' => $today]);
                
                $totalToday = array_sum($viewCounts);
                
                if (!$dailyStats) {
                    $dailyStats = new \App\Entity\DailyStats($today);
                    $dailyStats->setViewCount($totalToday);
                    $em->persist($dailyStats);
                } else {
                    $dailyStats->incrementViewCount($totalToday);
                }
                
                $em->flush();

                $connection->commit();
                $output->writeln("Da dong bo update view thanh cong cho " . count($viewCounts) . " bai viet (Tong: $totalToday views).");
            } catch (\Exception $e) {
                $connection->rollBack();
                $output->writeln("Loi khi cap nhat view: " . $e->getMessage());
                // Ghi lại vào log dự phòng nếu db chết đột xuất
                file_put_contents($logFile, $contents, FILE_APPEND | LOCK_EX);
            }
        } else {
            $output->writeln("Khong co id bai viet nao hop le de cap nhat.");
        }

        // Xóa file tạm
        if (file_exists($processingFile)) {
            unlink($processingFile);
        }
    }
}
