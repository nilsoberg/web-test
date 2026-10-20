<?php

namespace App\Command;

use App\Entity\JobStatusNotificationState;
use App\Repository\JobRepository;
use App\Repository\JobStatusNotificationStateRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:init-job-status-notifications',
    description: 'Initializes notification states for all existing jobs to prevent historical email spam.',
)]
class InitJobStatusNotificationsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly JobRepository $jobRepository,
        private readonly JobStatusNotificationStateRepository $stateRepository,
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'batch-size',
                null,
                InputOption::VALUE_OPTIONAL,
                'Number of records to process before flushing to the database.',
                100
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $batchSize = (int) $input->getOption('batch-size');
        
        if ($batchSize < 1) {
            $io->error('--batch-size must be a positive integer.');
            return Command::FAILURE;
        }

        $jobs = $this->jobRepository->findAll();
        
        $totalJobs = count($jobs);
        $io->info(sprintf('Found %d existing jobs to process.', $totalJobs));

        $initializedCount = 0;
        $skippedCount = 0;

        $this->entityManager->beginTransaction();

        try {
            foreach ($jobs as $index => $job) {
                $status = $job->getStatus();
                
                if ($status === null) {
                    $skippedCount++;
                    continue;
                }

                // Check if a notification state already exists to avoid duplicate constraint violations
                $existingState = $this->stateRepository->findOneByJob($job);
                
                if ($existingState === null) {
                    $state = (new JobStatusNotificationState())
                        ->setJob($job)
                        ->setLastNotifiedStatus($status->value)
                        ->setLastNotifiedAt(new \DateTimeImmutable());

                    $this->entityManager->persist($state);
                    $initializedCount++;
                } else {
                    $skippedCount++;
                }

                // Batch flush and clear to optimize memory management
                if (($initializedCount % $batchSize) === 0 && $initializedCount > 0) {
                    $this->entityManager->flush();
                }
            }

            // Flush remaining entities
            $this->entityManager->flush();
            $this->entityManager->commit();

        } catch (\Throwable $e) {
            $this->entityManager->rollback();
            $io->error(sprintf('An error occurred during initialization: %s', $e->getMessage()));
            return Command::FAILURE;
        }

        $io->success(sprintf(
            'Initialization complete. Successfully marked %d jobs as notified. Skipped %d jobs.',
            $initializedCount,
            $skippedCount
        ));

        return Command::SUCCESS;
    }
}
