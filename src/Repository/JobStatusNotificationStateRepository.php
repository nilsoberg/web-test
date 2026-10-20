<?php

namespace App\Repository;

use App\Entity\Job;
use App\Entity\JobStatusNotificationState;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<JobStatusNotificationState>
 */
class JobStatusNotificationStateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, JobStatusNotificationState::class);
    }

    public function findOneByJob(Job $job): ?JobStatusNotificationState
    {
        return $this->findOneBy(['job' => $job]);
    }
}
