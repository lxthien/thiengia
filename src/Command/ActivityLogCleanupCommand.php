<?php

namespace App\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ActivityLogCleanupCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('app:activity-log:cleanup')
            ->setDescription('Cleans up old activity logs')
            ->addOption('months', 'm', InputOption::VALUE_OPTIONAL, 'Number of months to keep', 6)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $months = (int) $input->getOption('months');

        if ($months < 1) {
            $io->error('Months must be at least 1.');
            return;
        }

        $date = new \DateTime();
        $date->modify("-{$months} months");

        $em = $this->getContainer()->get('doctrine.orm.entity_manager');
        
        $query = $em->createQuery('DELETE FROM App\Entity\ActivityLog l WHERE l.createdAt < :date')
            ->setParameter('date', $date);

        $deletedCount = $query->execute();

        $io->success("Successfully deleted {$deletedCount} old activity logs (older than {$months} months).");
    }
}
