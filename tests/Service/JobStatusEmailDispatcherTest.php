<?php

namespace App\Tests\Service;

use App\Auth\Entity\UserEntity;
use App\Email\EmailEventTag;
use App\Entity\ESTGenerateBlastJob;
use App\Service\EmailRecipientResolverService;
use App\Service\JobRetentionService;
use App\Service\JobStatusEmailDispatcherService;
use App\Service\JobSummaryService;
use App\Tests\UserHelperTrait;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Envelope;

class JobStatusEmailDispatcherTest extends TestCase
{
    use UserHelperTrait;

    public function testQueueBuildsContentAndQueuesEmail(): void
    {
        $jobId = 3001;
        $jobName = 'Status Job';

        $userMock = $this->createMock(UserEntity::class);
        $userMock->expects($this->once())
            ->method('isSendJobStatusEmail')
            ->willReturn(true);
        $job = (new ESTGenerateBlastJob())->setJobName($jobName)->setUser($userMock);
        $this->setJobId($job, $jobId);

        $summaryBuilder = $this->getSummaryService($job);

        $recipientResolver = $this->createMock(EmailRecipientResolverService::class);
        $recipientResolver->expects($this->once())
            ->method('resolveFromJob')
            ->with($job)
            ->willReturn('user@example.com');

        $retentionService = $this->createMock(JobRetentionService::class);
        $retentionService->expects($this->once())
            ->method('getRetentionDays')
            ->willReturn(14);

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function ($message) {
                return new Envelope($message);
            });

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('warning');
        $logger->expects($this->never())->method('error');

        $service = new JobStatusEmailDispatcherService(
            $recipientResolver,
            $retentionService,
            $summaryBuilder,
            $logger,
            $messageBus,
        );

        $this->assertTrue($service->queue($job, EmailEventTag::STARTED));
    }

    public function testQueueSkipsWhenRecipientEmailIsMissing(): void
    {
        $job = new ESTGenerateBlastJob();

        $summaryBuilder = $this->createMock(JobSummaryService::class);
        $retentionService = $this->createMock(JobRetentionService::class);

        $recipientResolver = $this->createMock(EmailRecipientResolverService::class);
        $recipientResolver->expects($this->once())
            ->method('resolveFromJob')
            ->with($job)
            ->willReturn(null);

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects($this->never())
            ->method('dispatch');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning');
        $logger->expects($this->never())->method('error');

        $service = new JobStatusEmailDispatcherService(
            $recipientResolver,
            $retentionService,
            $summaryBuilder,
            $logger,
            $messageBus,
        );

        $this->assertFalse($service->queue($job, EmailEventTag::STARTED));
    }

    private function getSummaryService(ESTGenerateBlastJob $job): JobSummaryService
    {
        $summaryBuilder = $this->createMock(JobSummaryService::class);
        $summaryBuilder->expects($this->once())
            ->method('getDisplaySummaryForJob')
            ->with($job, EmailEventTag::STARTED)
            ->willReturn([
                [
                    'label' => 'Job Number',
                    'value' => $job->getId(),
                    'is_html' => false,
                    'help' => '',
                ],
                [
                    'label' => 'Job Name',
                    'value' => $job->getJobName(),
                    'is_html' => false,
                    'help' => '',
                ],
            ]);

        return $summaryBuilder;
    }
}
