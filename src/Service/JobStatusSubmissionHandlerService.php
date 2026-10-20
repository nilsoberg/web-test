<?php

namespace App\Service;

use App\Dto\QueuedEmailContent;
use App\Email\EmailEventTag;
use App\Entity\Job;
use App\Entity\DisplayName;
use App\Enum\JobStatus;
use App\Enum\SummaryJobType;
use App\Message\SubmissionEmailMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class JobStatusSubmissionHandlerService
{
    public function __construct(
        private readonly JobStatusEmailDispatcherService $submissionEmailDispatcher,
        private readonly bool $sendOnSubmit,
    )
    {
    }

    /**
     * If enabled, sends an email when a job is submitted.
     * @param {Job} $job - job that is being sent
     */
    public function queue(Job $job): void
    {
        if ($this->sendOnSubmit) {
            $this->submissionEmailDispatcher->queue($job, \App\Email\EmailEventTag::SUBMITTED);
        }
    }
}
