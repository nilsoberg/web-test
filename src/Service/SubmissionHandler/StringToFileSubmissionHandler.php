<?php

namespace App\Service\SubmissionHandler;

use App\Form\Constants;
use App\Entity\Job;
use App\Service\JobUserResolverService;
use App\Service\StringToFileService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\FormInterface;

class StringToFileSubmissionHandler implements SubmissionHandlerInterface
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

        $pastedText = null;
        $fileExtension = null;

        // Loop over every form element and grab the submitted string
        foreach ($form as $child) {
            $flag = $child->getConfig()->getOption(Constants::PROCESSING_FLAG);

            if ($flag === Constants::STRING_TO_FILE) {
                $pastedText = $child->getData();
                $pastedText = preg_replace('/[\r\n\s]+/s', '', $pastedText);
                $fileExtension = $child->getConfig()->getOption(Constants::FILE_EXTENSION);
            } else if ($flag === Constants::BLAST_SEQ_TO_FILE) {
                $pastedText = $child->getData();

                // Remove fasta header
                if (preg_match('/^>/', $pastedText)) {
                    $pastedText = preg_replace('/^>[^\r\n]+[\r\n](.+)$/s', '$1', $pastedText);
                }

                $pastedText = preg_replace('/[\r\n\s]+/s', '', $pastedText);

                $fileExtension = $child->getConfig()->getOption(Constants::FILE_EXTENSION);
            }
        }

        $file = $this->stringToFileService->createUploadedFileFromString($pastedText, $fileExtension ?? 'txt');

        $this->entityManager->persist($job);
        $this->entityManager->flush();

        $job->setJobFile($file);

        $this->entityManager->flush();
    }
}
