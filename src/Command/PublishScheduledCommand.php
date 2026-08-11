<?php

namespace App\Command;

use App\Entity\News;
use App\Enum\PostStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Tự động xuất bản bài viết/trang đang "Đặt lịch" khi đã tới giờ hẹn.
 *
 * Chạy qua cron mỗi phút:
 *   * * * * * cd /path/to/project && php bin/console app:publish-scheduled >> var/log/scheduled.log 2>&1
 */
class PublishScheduledCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('app:publish-scheduled')
            ->setDescription('Tự động chuyển bài viết/trang "Đặt lịch" sang "Đã xuất bản" khi đã tới giờ hẹn.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $now = new \DateTime('now', new \DateTimeZone('Asia/Ho_Chi_Minh'));

        $scheduled = $this->em->getRepository(News::class)->createQueryBuilder('n')
            ->where('n.status = :status')
            ->andWhere('n.scheduledAt <= :now')
            ->setParameter('status', PostStatus::Scheduled)
            ->setParameter('now', $now)
            ->getQuery()
            ->getResult();

        if (empty($scheduled)) {
            $output->writeln('Khong co bai viet nao den gio xuat ban.');

            return Command::SUCCESS;
        }

        foreach ($scheduled as $news) {
            $news->setStatus(PostStatus::Published);
        }

        $this->em->flush();

        $output->writeln(sprintf('Da xuat ban %d bai viet/trang dat lich.', count($scheduled)));

        return Command::SUCCESS;
    }
}
