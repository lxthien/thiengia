<?php

namespace App\Command;

use App\Entity\ContentDecaySnapshot;
use App\Entity\News;
use App\Enum\PostStatus;
use App\Seo\ContentDecayReporter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Tính lại điểm content decay cho mọi bài viết đã xuất bản và lưu vào
 * content_decay_snapshot — ContentDecayController chỉ đọc từ bảng này,
 * không tự phân tích (word count, v.v.) lúc admin mở trang.
 *
 * Chạy qua cron, ví dụ mỗi giờ (đủ nhanh để phản ánh bài mới sửa, không cần
 * mỗi phút như app:publish-scheduled vì điểm decay đổi chậm theo thời gian):
 *   0 * * * * cd /path/to/project && php bin/console app:compute-content-decay >> var/log/content-decay.log 2>&1
 */
class ComputeContentDecayCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ContentDecayReporter $reporter,
    ) {
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('app:compute-content-decay')
            ->setDescription('Tính lại điểm content decay cho mọi bài viết đã xuất bản, lưu vào content_decay_snapshot.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $posts = $this->em->getRepository(News::class)->createQueryBuilder('n')
            ->where('n.postType = :postType')
            ->andWhere('n.status = :status')
            ->setParameter('postType', 'post')
            ->setParameter('status', PostStatus::Published)
            ->getQuery()
            ->getResult();

        $snapshotRepo = $this->em->getRepository(ContentDecaySnapshot::class);
        $now = new \DateTime();
        $seenIds = [];

        foreach ($posts as $post) {
            $analysis = $this->reporter->analyze($post, $now);

            $snapshot = $snapshotRepo->findOneBy(['news' => $post]);
            if (!$snapshot) {
                $snapshot = new ContentDecaySnapshot($post);
                $this->em->persist($snapshot);
            }

            $snapshot
                ->setDecayScore($analysis['decayScore'])
                ->setDecayStatus($analysis['decayStatus'])
                ->setAgeDays($analysis['ageDays'])
                ->setViews($analysis['views'])
                ->setSeoScore($analysis['seo']['score'])
                ->setSeoStatus($analysis['seo']['status'])
                ->setReasons($analysis['reasons'])
                ->setRecommendations($analysis['recommendations'])
                ->setIsIndexable($analysis['isIndexable'])
                ->setGeneratedAt($now);

            $seenIds[] = $post->getId();
        }

        $this->em->flush();

        // Xoá snapshot của bài không còn published/post (chuyển draft, xoá...)
        // để danh sách Content Decay không hiện bài đã ẩn.
        $deleted = 0;
        if (!empty($seenIds)) {
            $deleted = $this->em->createQueryBuilder()
                ->delete(ContentDecaySnapshot::class, 's')
                ->where('IDENTITY(s.news) NOT IN (:ids)')
                ->setParameter('ids', $seenIds)
                ->getQuery()
                ->execute();
        } else {
            $deleted = $this->em->createQueryBuilder()
                ->delete(ContentDecaySnapshot::class, 's')
                ->getQuery()
                ->execute();
        }

        $output->writeln(sprintf('Da tinh content decay cho %d bai viet, xoa %d snapshot cu.', count($posts), $deleted));

        return Command::SUCCESS;
    }
}
