<?php

namespace App\Service;

use App\Entity\Job;
use App\Repository\JobRepository;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Uid\Uuid;

class JobRetrieverService
{
    public function __construct(
        private readonly JobRepository $jobRepository,
        private readonly LoggerInterface $logger,
        private readonly Security $security,
    )
	{
    }

    /**
     * Retrieve a Job object from the database using the specified ID.
     * @param {int} $jobId - ID of the job
     * @param {string} $jobKey - UUID/key for the job (used for security)
     * @return {?Job} - Job object; null if not found
     */
    public function findJob(int $jobId, string $jobKey): ?Job
    {
        if ($jobId < 0 || !Uuid::isValid($jobKey)) {
            $this->logger->info("Invalid job parameters: ID {$jobId}, key {$jobKey}.");
            return null;
        }

        $job = $this->jobRepository->findOneBy(['id' => $jobId, 'uuid' => $jobKey]);

        if (!$job) {
            $this->logger->info("Unable to find job: {$jobId} {$jobKey}.");
            return null;
        }

        // Ensure the user has permission to retrieve this job
        if (!$this->security->isGranted('JOB_VIEW', $job)) {
            $this->logger->warning("Access denied for job {$jobId} to user " . $this->security->getUser()?->getUserIdentifier());
            return null;
        }

        return $job;
    }
}
