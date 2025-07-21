<?php

namespace App\Command;

use App\Repository\LogRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:purge-old-logs',
    description: 'Supprime les logs de plus de 6 mois',
)]
class PurgeOldLogsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly LogRepository $logRepository
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $sixMonthsAgo = new \DateTimeImmutable('-6 months');
        $logs = $this->logRepository->createQueryBuilder('l')
            ->where('l.date < :limit')
            ->setParameter('limit', $sixMonthsAgo)
            ->getQuery()
            ->getResult();

        foreach ($logs as $log) {
            $this->em->remove($log);
        }

        $this->em->flush();
        $output->writeln(count($logs) . ' logs supprimés.');

        return Command::SUCCESS;
    }
}
