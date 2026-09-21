<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use App\Service\SitemapService;

class GenerateSitemapCommand extends Command
{
    public function __construct(private readonly SitemapService $sitemapService)
    {
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('app:sitemap:generate')
            ->setDescription('Display the bounded XML sitemap index manifest.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('<info>Sitemap index is available at: /sitemap.xml</info>');
        $output->writeln(sprintf('<comment>Indexable URLs: %d</comment>', $this->sitemapService->getUrlCount()));
        $output->writeln(sprintf('<comment>Sitemap files: %d (max %d URLs/file)</comment>', $this->sitemapService->getSitemapPageCount(), SitemapService::URLS_PER_SITEMAP));

        return Command::SUCCESS;
    }
}
