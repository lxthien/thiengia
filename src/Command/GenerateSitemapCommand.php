<?php

namespace App\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;
use App\Service\SitemapService;

class GenerateSitemapCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('app:sitemap:generate')
            ->setDescription('Generate XML sitemap');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $sitemapService = $this->getContainer()->get(SitemapService::class);
        $urls = $sitemapService->generateSitemap();

        $output->writeln('<info>Sitemap Generated Successfully!</info>');
        $output->writeln('');
        $output->writeln(sprintf('<comment>Total URLs: %d</comment>', count($urls)));
        $output->writeln('');

        // Display summary
        $table = new Table($output);
        $table->setHeaders(['URL', 'Last Modified', 'Change Freq', 'Priority']);
        
        foreach ($urls as $url) {
            $table->addRow([
                $url['url'],
                $url['lastmod'],
                $url['changefreq'],
                $url['priority']
            ]);
        }
        
        $table->render();
        
        $output->writeln('');
        $output->writeln('<info>Sitemap is available at: /sitemap.xml</info>');
    }
}
