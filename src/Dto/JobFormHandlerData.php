<?php

namespace App\Dto;

use App\Entity\Job;
use App\Service\SubmissionHandler\SubmissionHandlerInterface;
use Symfony\Component\Form\FormInterface;

class JobFormHandlerData {
    public function __construct(
        public string $formTypeName, 
        public FormInterface $form,
        public Job $job,
        public ?SubmissionHandlerInterface $handler = null,
    )
    {
    }
}
