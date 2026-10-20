<?php

namespace App\Repository;

use App\Auth\Entity\UserEntity;
use App\Entity\Job;
use App\Enum\JobStatus;
use App\Enum\JobSummarySortOrder;
use App\Enum\SummaryJobType;
use App\Service\JobRetentionService;
use DateTime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @extends ServiceEntityRepository<Job>
 */
class JobRepository extends ServiceEntityRepository
{
    private $metadataService;

    public function __construct(ManagerRegistry $registry, JobRetentionService $metadataService)
    {
        parent::__construct($registry, Job::class);
        $this->metadataService = $metadataService;
    }

    public function getJobsByUserAndType(UserInterface $user, SummaryJobType $jobType): array
    {
        $expiredDate = $this->metadataService->getJobExpirationDate($user);

        $qb = $this->createQueryBuilder('j')
            ->andWhere('j.user = :user')
            ->andWhere('j.timeCreated > :expired')
            ->setParameter('user', $user)
            ->setParameter('expired', $expiredDate);

        $userJobs = $qb->getQuery()->getResult();

        $filteredJobs = array();
        foreach ($userJobs as $job) {
            if ($job->getSummaryJobType() === $jobType) {
                $filteredJobs[] = $job;
            }
        }

        return $filteredJobs;
    }

    public function getSummaryJobs(UserInterface $user, SummaryJobType $jobType, JobSummarySortOrder $sortBy = JobSummarySortOrder::DateCompleted, bool $sortAsc = false): array
    {
        $expiredDate = $this->metadataService->getJobExpirationDate($user);

        $sortDirection = $sortAsc ? 'ASC' : 'DESC';
        //$expirationDate = $this->getExpirationDate();

        // Define the CASE expression for custom status ordering.
        $statusOrderExpression = "CASE j.status " .
                                 "WHEN 'new' THEN 1 " .
                                 "WHEN 'running' THEN 2 " .
                                 "WHEN 'finished' THEN 3 " .
                                 "WHEN 'failed' THEN 4 " .
                                 "ELSE 5 END";

        $qb = $this->createQueryBuilder('j')
            ->addSelect("(" . $statusOrderExpression . ") AS HIDDEN statusOrdering")
            ->andWhere('j.user = :user')
//            ->andWhere('j.timeCreated < ' . $expiredDate)
            ->setParameter('user', $user)
            ->orderBy('statusOrdering', 'ASC');

        // Sort by timeCompleted if the job is finished, otherwise sort by timeCreated
        $conditionalDateExpression = "CASE " .
                                     "WHEN j.status = 'finished' THEN j.timeCompleted " .
                                     "ELSE j.timeCreated END";
        $qb->addSelect("(" . $conditionalDateExpression . ") AS HIDDEN effectiveSortDate");
        if ($sortBy === JobSummarySortOrder::ID) {
            $qb->addOrderBy('j.id', $sortDirection);
        } else if ($sortBy === JobSummarySortOrder::DateCreated) {
            $qb->addOrderBy('j.timeCreated', $sortDirection);
        } else {
            $qb->addOrderBy('effectiveSortDate', $sortDirection);
        }

        $userJobs = $qb->getQuery()->getResult();

        // Only get the jobs that the user requests
        // There's probably a more elegant way of doing this but this works for now
        $filteredJobs = array();
        foreach ($userJobs as $job) {
            if ($job->getSummaryJobType() === $jobType
                && $job->getStatus() !== JobStatus::Archived
                && $job->getStatus() !== JobStatus::Cancelled
                && $job->getStatus() !== JobStatus::Cancelled)
            {
                $filteredJobs[] = $job;
            }
        }

        return $filteredJobs;
    }

    public function getExpirationDate(): ?\DateTimeInterface
    {
        return null;
    }

    public function findByUser(UserInterface $user)
    {
        return $this->createQueryBuilder('j')
            ->andWhere('j.user = :user')
            ->setParameter('user', $user)
            ->orderBy('j.timeCreated', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param array<JobStatus|string> $statuses
     * @return Job[]
     */
    public function findByStatuses(array $statuses, int $limit = 200): array
    {
        if ($limit < 1) {
            return [];
        }

        $normalizedStatuses = [];
        foreach ($statuses as $status) {
            $value = $status instanceof JobStatus ? $status->value : mb_strtolower(trim((string) $status));
            if ($value !== '') {
                $normalizedStatuses[] = $value;
            }
        }

        if ($normalizedStatuses === []) {
            return [];
        }

        return $this->createQueryBuilder('j')
            ->andWhere('j.status IN (:statuses)')
            ->setParameter('statuses', array_values(array_unique($normalizedStatuses)))
            ->orderBy('j.id', 'DESC') // Order by most recent submission
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Collect the list of jobs for the admin display panel.  Jobs returned are within the scope
     * of the user (e.g. if the user is an org admin, they can only see what is available to them
     * in the org).
     * @param {UserEntity} $currentUser - the requesting user
     * @param {DateTimeInterface} $startOfMonth - the start date/time of the month to view
     * @param {DateTimeInterface} $endOfMonth - the end date/time of the month to view
     * @param {array} $jobClasses - optional array of job classes to return (e.g. to filter only EST)
     * @param {bool} $isSuperAdmin - true if the user is a super admin
     * @param {bool} $isOrgAdmin - true if the user is an org admin
     * @return {array} array of Job objects
     */
    public function findJobsForAdminView(
        UserEntity $currentUser,
        \DateTimeInterface $startOfMonth,
        \DateTimeInterface $endOfMonth,
        array $jobClasses = [],
        bool $isSuperAdmin = false,
        bool $isOrgAdmin = false,
    ): array
    {
        $qb = $this->createQueryBuilder('j')
            ->leftJoin('j.user', 'u')->addSelect('u')
            ->leftJoin('u.primaryOrganization', 'o')->addSelect('o')
            ->andWhere('j.timeCreated >= :start')
            ->andWhere('j.timeCreated < :end')
            ->setParameter('start', $startOfMonth)
            ->setParameter('end', $endOfMonth)
            ->orderBy('j.timeCreated', 'DESC');

        // Scope enforcement
        if (!$isSuperAdmin) {
            if ($isOrgAdmin && $currentUser->getPrimaryOrganization()) {
                // Org Admins see all jobs in their organization
                $qb->andWhere('u.primaryOrganization = :org')
                   ->setParameter('org', $currentUser->getPrimaryOrganization());
            } else {
                // Regular users see only their own jobs
                $qb->andWhere('j.user = :user')
                   ->setParameter('user', $currentUser);
            }
        }

        // Job type filtering via discriminator class names (e.g. ESTGenerateAccessionJob::class)
        if (!empty($jobClasses)) {
            $orX = $qb->expr()->orX();
            foreach ($jobClasses as $class) {
                $orX->add($qb->expr()->isInstanceOf('j', $class));
            }
            $qb->andWhere($orX);
        }

        return $qb->getQuery()->getResult();
    }
}
