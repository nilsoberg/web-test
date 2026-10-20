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

class JobStatusEmailDispatcherService
{
    public function __construct(
        private readonly EmailRecipientResolverService $resolverService,
        private readonly JobRetentionService $retentionService,
        private readonly JobSummaryService $summaryService,
        private readonly LoggerInterface $logger,
        private readonly MessageBusInterface $messageBus,
    )
    {
    }

    /**
     * Prepares and queues a message that will get converted to an email by the message handler
     * in a future step.
     * @param {Job} $job - job that is being sent
     * @param {string} $eventTag - matches one of the tags in \App\Email\EmailEventTag
     */
    public function queue(Job $job, string $eventTag): bool
    {
        // Double-check that the email address is valid before sending it
        $normalizedEmail = $this->resolverService->resolveFromJob($job);
        if (!$normalizedEmail) {
            $this->logger->warning('Skipping email because recipient email is missing or invalid.', [
                'job_id' => $job->getId(),
                'recipient_email' => $job->getUser()?->getEmail() ?? '',
                'event_tag' => $eventTag,
            ]);
            return false;
        }

        // Early exit if the job status email setting is off
        if (!$job->getUser()?->isSendJobStatusEmail()) {
            return true;
        }

        $subType = $this->getSubType($job);

        // Get job parameters from the summary service
        $context = $this->getJobContext($job, $subType, $eventTag);

        try {
            // Prepare an email
            $message = $this->buildEmail($job, $normalizedEmail, $context, $eventTag, $subType);

            // Add the message to the message bus for sending by Symfony later
            if (!$this->queueMessage($message, $eventTag)) {
                return false;
            }
        } catch (\Throwable $e) {
            $this->logger->error('Failed to build job-status email.', [
                'job_id' => $job->getId(),
                'recipient_email' => $normalizedEmail,
                'event_tag' => $eventTag,
                'error' => $e->getMessage(),
            ]);
            return false;
        }

        return true;
    }

    /**
     * Adds a message to the message bus.
     */
    private function queueMessage(QueuedEmailContent $payload, string $eventTag): bool
    {
        $message = new SubmissionEmailMessage(
            recipientEmail: $payload->recipientEmail,
            subject: $payload->subject,
            context: $payload->context,
            messagePurpose: $eventTag,
        );

        try {
            $this->messageBus->dispatch($message);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to queue submission email.', [
                'recipient_email' => $payload->recipientEmail,
                'subject' => $payload->subject,
                'event_tag' => $eventTag,
                'context' => $payload->context,
                'error' => $e->getMessage(),
            ]);
            return false;
        }

        return true;
    }

    /**
     * Build an email message that is queued.
     */
    private function buildEmail(Job $job, string $email, array $context, string $eventTag, string $subType): QueuedEmailContent
    {
        $subjectAction = $this->getSubjectAction($eventTag);

        $mergedSubject = "EFI - $subType analysis - $subjectAction";

        return new QueuedEmailContent(
            recipientEmail: $email,
            subject: $mergedSubject,
            context: $context,
        );
    }

    private function getSubjectAction(string $eventTag) {
        return match($eventTag) {
            EmailEventTag::SUBMITTED => 'Submitted',
            EmailEventTag::STARTED => 'Started',
            EmailEventTag::FINISHED => 'Finished',
            EmailEventTag::FAILED => 'Failed',
            default => ''
        };
    }

    private function getSubType(Job $job) {
        $prefix = '';
        if ($job->getSummaryJobType() === SummaryJobType::EST &&
            ($job instanceof \App\Entity\ESTGenerateAccessionJob ||
             $job instanceof \App\Entity\ESTGenerateBlastJob ||
             $job instanceof \App\Entity\ESTGenerateFamiliesJob ||
             $job instanceof \App\Entity\ESTGenerateFastaJob))
        {
            $prefix = 'EST ';
        } else if ($job->getSummaryJobType() === SummaryJobType::GND) {
            $prefix = 'GND ';
        }

        return $prefix . DisplayName::getFrom($job);
    }

    /**
     * Create the job context data that will get passed to the message handler and injected
     * into email templates.
     */
    private function getJobContext(Job $job, string $subType, string $viewPurpose): array
    {
        // Process email using the job summary yaml config
        $summaryData = $this->summaryService->getDisplaySummaryForJob($job, $viewPurpose);

        $baseData = [
            'retention_days' => $this->retentionService->getRetentionDays($job->getUser()),
        ];
            
        $summary = [];
        foreach ($summaryData as $item) {
            if ($item['label'] === 'resultsLink') {
                $baseData['results_link'] = $item['value'];
            } else {
                $summary[] = $item;
            }
        }

        $context = [
            'base' => $baseData,
            'summary' => $summary,
        ];

        return $context;
    }
}
