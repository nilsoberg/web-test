<?php

namespace App\Service\SubmissionHandler;

use App\Entity\Job;
use App\Service\JobUserResolverService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\FormInterface;

class BasicSubmissionHandler implements SubmissionHandlerInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly JobUserResolverService $jobUserResolver,
    )
    {
    }

    public function handle(FormInterface $form, Job $job): void
    {
        $user = $this->jobUserResolver->resolve($form);
        $job->setUser($user);
        $this->entityManager->persist($job);
        $this->entityManager->flush();
    }
}
