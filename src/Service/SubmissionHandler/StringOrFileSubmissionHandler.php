<?php

namespace App\Service\SubmissionHandler;

use App\Form\Constants;
use App\Entity\Job;
use App\Service\JobUserResolverService;
use App\Service\StringToFileService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\FormInterface;

class StringOrFileSubmissionHandler implements SubmissionHandlerInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly JobUserResolverService $jobUserResolver,
        private readonly StringToFileService $stringToFileService,
    )
    {
    }

    public function handle(FormInterface $form, Job $job): void
    {
        $user = $this->jobUserResolver->resolve($form);
        $job->setUser($user);

        $this->entityManager->persist($job);
        $this->entityManager->flush();

        $pastedText = null;
        $fileExtension = null;
        $uploadedFile = null;

        foreach ($form as $child) {
            $flag = $child->getConfig()->getOption(Constants::PROCESSING_FLAG);

            if ($flag === Constants::STRING_TO_FILE && !$pastedText) {
                $pastedText = $child->getData();
                $fileExtension = $child->getConfig()->getOption(Constants::FILE_EXTENSION);
            } else if ($flag === Constants::UPLOADED_FILE && !$uploadedFile) {
                $uploadedFile = $child->getData();
            }
        }

        if ($pastedText) {
            $uploadedFile = $this->stringToFileService->createUploadedFileFromString($pastedText, $fileExtension ?? 'txt');
        }

        $job->setJobFile($uploadedFile);

        $this->entityManager->flush();
    }
}
