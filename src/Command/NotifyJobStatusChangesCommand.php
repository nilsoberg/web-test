<?php

namespace App\Command;

use App\Email\EmailEventTag;
use App\Entity\JobStatusNotificationState;
use App\Enum\JobStatus;
use App\Repository\JobRepository;
use App\Repository\JobStatusNotificationStateRepository;
use App\Service\JobStatusEmailDispatcherService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:notify-job-status-changes',
    description: 'Queues async emails for jobs whose status has changed.',
)]
class NotifyJobStatusChangesCommand extends Command
{
    /** @var JobStatus[] */
    private const DEFAULT_STATUSES = [
        JobStatus::Finished,
        JobStatus::Failed,
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly JobRepository $jobRepository,
        private readonly JobStatusEmailDispatcherService $statusEmailDispatcher,
        private readonly JobStatusNotificationStateRepository $stateRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'limit',
                null,
                InputOption::VALUE_OPTIONAL,
                'Maximum number of candidate jobs to scan per run.',
                200
            )
            ->addOption(
                'statuses',
                null,
                InputOption::VALUE_OPTIONAL,
                "Comma-separated statuses to notify (e.g. 'finished,failed').",
                'finished,failed'
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Output the jobs that would be notified without actually dispatching the message',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $limit = (int) $input->getOption('limit');
        if ($limit < 1) {
            $io->error('--limit must be a positive integer.');
            return Command::FAILURE;
        }

        $targetStatuses = $this->parseStatuses((string) $input->getOption('statuses'));
        if ($targetStatuses === []) {
            $io->error('No valid statuses were provided. Valid values: new,running,finished,failed,archived');
            return Command::FAILURE;
        }

        $isDryRun = (bool) $input->getOption('dry-run');
        if ($isDryRun) {
            $io->note('Dry run enabled: No emails will be queued and database states will not be modified.');
        }

        $jobs = $this->jobRepository->findByStatuses($targetStatuses, $limit);

        $scanned = 0;
        $queued = 0;
        $skippedUnchanged = 0;
        $skippedDispatch = 0;

        foreach ($jobs as $job) {
            ++$scanned;

            $status = $job->getStatus();
            if ($status === null) {
                ++$skippedDispatch;
                continue;
            }

            $state = $this->stateRepository->findOneByJob($job);
            if ($state !== null && $state->getLastNotifiedStatus() === $status->value) {
                ++$skippedUnchanged;
                continue;
            }

            $eventTag = $this->getEventTag($status);
            
            if ($isDryRun) {
                if ($output->isVerbose()) {
                    $io->text(sprintf('[Info] Would queue email for Job ID %d (Status: %s)', $job->getId(), $status->value));
                }
                ++$queued;
                continue; 
            }

            $queuedSuccessfully = $this->statusEmailDispatcher->queue($job, $eventTag);
            if (!$queuedSuccessfully) {
                ++$skippedDispatch;
                continue;
            }

            if ($state === null) {
                $state = (new JobStatusNotificationState())->setJob($job);
                $this->entityManager->persist($state);
            }

            $state
                ->setLastNotifiedStatus($status->value)
                ->setLastNotifiedAt(new \DateTimeImmutable());

            ++$queued;
        }

        if (!$isDryRun) {
            $this->entityManager->flush();
        }

        $label = $isDryRun ? 'Dry run complete' : 'Job-status notification run complete';

        $nowTime = new \DateTime();
        $now = $nowTime->format('Y-m-d H:i:s');

        $io->success(sprintf(
            '[%s] %s. scanned=%d queued=%d skipped_unchanged=%d skipped_dispatch=%d',
            $now,
            $label,
            $scanned,
            $queued,
            $skippedUnchanged,
            $skippedDispatch
        ));

        return Command::SUCCESS;
    }

    /**
     * Returns an event tag corresponding to the job status.  The event tag is used by the email
     * dispatcher to get the proper email content builder.
     * @param {JobStatus} $status - Job status
     * @return {string} an identifier that corresponds to one of the tagged items in App\Email\*Builder.php
     */
    private function getEventTag(JobStatus $status): string
    {
        $eventTag = match ($status) {
            JobStatus::New => EmailEventTag::SUBMITTED,
            JobStatus::Running => EmailEventTag::STARTED,
            JobStatus::Finished => EmailEventTag::FINISHED,
            JobStatus::Failed => EmailEventTag::FAILED,
            default => EmailEventTag::GENERIC
        };
        return $eventTag;
    }

    /**
     * @return JobStatus[]
     */
    private function parseStatuses(string $raw): array
    {
        $requestedValues = array_filter(array_map(
            static fn(string $item): string => mb_strtolower(trim($item)),
            explode(',', $raw)
        ));

        if ($requestedValues === []) {
            return self::DEFAULT_STATUSES;
        }

        $validStatusMap = [];
        foreach (JobStatus::cases() as $case) {
            $validStatusMap[$case->value] = $case;
        }

        $result = [];
        foreach (array_values(array_unique($requestedValues)) as $value) {
            if (isset($validStatusMap[$value])) {
                $result[] = $validStatusMap[$value];
            }
        }

        return $result;
    }
}
