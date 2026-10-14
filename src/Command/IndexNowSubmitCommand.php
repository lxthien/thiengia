<?php

namespace App\Command;

use App\Seo\IndexNow\IndexNowClient;
use App\Service\SitemapService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Gửi URL lên IndexNow bằng tay:
 *
 *   php bin/console app:indexnow:submit https://example.com/bai-viet/
 *   php bin/console app:indexnow:submit --all     # mọi URL trong sitemap
 *
 * Bài viết/trang được đăng hoặc sửa trong admin đã tự gửi qua
 * App\EventListener\IndexNowListener; lệnh này dùng cho lần đầu bật IndexNow
 * (--all) hoặc để kiểm tra khóa. Với --all cần DEFAULT_URI đúng tên miền thật.
 */
class IndexNowSubmitCommand extends Command
{
    public function __construct(
        private readonly IndexNowClient $client,
        private readonly SitemapService $sitemapService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('app:indexnow:submit')
            ->setDescription('Gửi URL lên IndexNow (Bing, Yandex...) để được thu thập lại sớm.')
            ->addArgument('urls', InputArgument::IS_ARRAY, 'Các URL tuyệt đối cần gửi')
            ->addOption('all', null, InputOption::VALUE_NONE, 'Gửi toàn bộ URL đang có trong sitemap');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!$this->client->isEnabled()) {
            $io->error('Chưa bật IndexNow: đặt INDEXNOW_KEY (8–128 ký tự a-f, 0-9) trong .env.local. Tạo khóa: php -r "echo bin2hex(random_bytes(16));"');

            return Command::FAILURE;
        }

        $urls = $input->getArgument('urls');

        if ($input->getOption('all')) {
            for ($page = 1; $page <= $this->sitemapService->getSitemapPageCount(); ++$page) {
                foreach ($this->sitemapService->generateSitemapPage($page) as $entry) {
                    $urls[] = $entry['url'];
                }
            }
        }

        if ($urls === []) {
            $io->warning('Không có URL nào. Truyền URL làm tham số hoặc dùng --all.');

            return Command::INVALID;
        }

        $result = $this->client->submit($urls);

        if (!$result['ok']) {
            $io->error($result['message']);

            return Command::FAILURE;
        }

        $io->success(sprintf('%s (%d URL, HTTP %d)', $result['message'], $result['sent'], $result['status']));

        return Command::SUCCESS;
    }
}
