<?php

namespace App\Command;

use App\Repository\InternalLinkReportRepository;
use App\Seo\InternalLink\InternalLinkAnalyzer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Quét link nội bộ của mọi bài viết, trang, danh mục và menu rồi lưu vào
 * internal_link_report — InternalLinkController chỉ đọc từ bảng này.
 *
 * Chạy qua cron, ví dụ mỗi đêm:
 *   15 2 * * * cd /path/to/project && php bin/console app:compute-internal-links >> var/log/internal-links.log 2>&1
 */
class ComputeInternalLinksCommand extends Command
{
    public function __construct(
        private readonly InternalLinkAnalyzer $analyzer,
        private readonly InternalLinkReportRepository $reports,
    ) {
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('app:compute-internal-links')
            ->setDescription('Phân tích link nội bộ (trang mồ côi, link hỏng, anchor text...) và lưu vào internal_link_report.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $summary = $this->reports->refresh($this->analyzer)->getData()['summary'];

        $output->writeln(sprintf(
            'Da luu bao cao link noi bo: %d trang, %d link noi bo, %d trang mo coi, %d link hong, %d goi y dat link.',
            $summary['pages'],
            $summary['internalLinks'],
            $summary['orphan'],
            $summary['broken'],
            $summary['opportunities']
        ));

        return Command::SUCCESS;
    }
}
