<?php

namespace App\Command;

use App\Entity\HealthReport;
use App\Entity\News;
use App\Health\HealthAuditManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Chạy HealthAuditManager::buildReport() (quét link hỏng, ảnh mất file, nội
 * dung năm cũ, dung lượng upload...) và lưu kết quả vào health_report —
 * HealthController chỉ đọc bản mới nhất, không tự audit lại lúc admin mở
 * trang (buildReport() DOM-parse toàn bộ nội dung published, tốn kém nếu
 * chạy mỗi request).
 *
 * Chạy qua cron, ví dụ mỗi giờ (giống app:compute-content-decay):
 *   0 * * * * cd /path/to/project && php bin/console app:compute-health-report >> var/log/health-report.log 2>&1
 */
class ComputeHealthReportCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly HealthAuditManager $healthAudit,
    ) {
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('app:compute-health-report')
            ->setDescription('Chạy audit sức khỏe nội dung/hạ tầng, lưu kết quả vào health_report.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $report = $this->healthAudit->buildReport();
        $generatedAt = $report['generatedAt'];
        unset($report['generatedAt']);

        $report['brokenLinks'] = array_map(
            fn (array $item) => [...$item, 'post' => $this->newsToArray($item['post'])],
            $report['brokenLinks']
        );
        $report['postsWithoutImage'] = array_map(
            fn (News $post) => $this->newsToArray($post),
            $report['postsWithoutImage']
        );
        $report['outdatedPosts'] = array_map(
            fn (array $item) => [...$item, 'post' => $this->newsToArray($item['post'])],
            $report['outdatedPosts']
        );
        // missingImages đã là mảng phẳng (source/title/path/editUrl), không có entity.

        // Chỉ giữ 1 bản mới nhất, không cần lịch sử như DailyStats.
        $this->em->createQueryBuilder()
            ->delete(HealthReport::class, 'r')
            ->getQuery()
            ->execute();

        $entity = new HealthReport();
        $entity->setGeneratedAt($generatedAt);
        $entity->setData($report);
        $this->em->persist($entity);
        $this->em->flush();

        $output->writeln(sprintf(
            'Da luu health report: %d link hong, %d anh mat file, %d bai thieu anh, %d bai chua thong tin cu.',
            count($report['brokenLinks']),
            count($report['missingImages']),
            count($report['postsWithoutImage']),
            count($report['outdatedPosts'])
        ));

        return Command::SUCCESS;
    }

    private function newsToArray(News $post): array
    {
        return [
            'id' => $post->getId(),
            'title' => $post->getTitle(),
            'url' => $post->getUrl(),
            'isPage' => $post->isPage(),
        ];
    }
}
