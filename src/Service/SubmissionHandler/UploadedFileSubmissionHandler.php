<?php

namespace App\Service\SubmissionHandler;

use App\Form\Constants;
use App\Entity\Job;
use App\Service\JobUserResolverService;
use App\Service\StringToFileService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\FormInterface;

class UploadedFileSubmissionHandler implements SubmissionHandlerInterface
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

        $file = null;

        foreach ($form as $child) {
            $flag = $child->getConfig()->getOption(Constants::PROCESSING_FLAG);

            if ($flag === Constants::UPLOADED_FILE) {
                $file = $child->getData();
            }
        }

        $this->entityManager->persist($job);
        $this->entityManager->flush();

        $job->setJobFile($file);
        $job->setJobName($job->getUploadedFilename());

        $this->entityManager->flush();
    }
}
