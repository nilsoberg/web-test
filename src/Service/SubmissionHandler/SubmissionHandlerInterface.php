<?php

namespace App\Service\SubmissionHandler;

use App\Entity\Job;
use Symfony\Component\Form\FormInterface;

interface SubmissionHandlerInterface
{
    public function handle(FormInterface $form, Job $job): void;
}
