<?php

namespace App\Tests\Command;

use App\Command\NotifyJobStatusChangesCommand;
use App\Email\EmailEventTag;
use App\Entity\ESTGenerateBlastJob;
use App\Entity\JobStatusNotificationState;
use App\Enum\JobStatus;
use App\Repository\JobRepository;
use App\Repository\JobStatusNotificationStateRepository;
use App\Service\JobStatusEmailDispatcherService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class NotifyJobStatusChangesCommandTest extends TestCase
{
    public function testExecuteQueuesEmailAndPersistsNotificationState(): void
    {
        $job = (new ESTGenerateBlastJob())->setStatus(JobStatus::Finished);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(JobStatusNotificationState::class));
        $entityManager->expects($this->once())->method('flush');

        $jobRepository = $this->createMock(JobRepository::class);
        $jobRepository->expects($this->once())
            ->method('findByStatuses')
            ->with([JobStatus::Finished], 10)
            ->willReturn([$job]);

        $dispatcher = $this->createMock(JobStatusEmailDispatcherService::class);
        $dispatcher->expects($this->once())
            ->method('queue')
            ->with($job, EmailEventTag::FINISHED)
            ->willReturn(true);

        $stateRepository = $this->createMock(JobStatusNotificationStateRepository::class);
        $stateRepository->expects($this->once())
            ->method('findOneByJob')
            ->with($job)
            ->willReturn(null);

        $tester = $this->createCommandTester(new NotifyJobStatusChangesCommand(
            $entityManager,
            $jobRepository,
            $dispatcher,
            $stateRepository,
        ));

        $exitCode = $tester->execute([
            '--statuses' => 'finished',
            '--limit' => 10,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('queued=1', $tester->getDisplay());
        $this->assertStringContainsString('skipped_unchanged=0', $tester->getDisplay());
    }

    public function testExecuteSkipsWhenStatusAlreadyNotified(): void
    {
        $job = (new ESTGenerateBlastJob())->setStatus(JobStatus::Finished);
        $existingState = (new JobStatusNotificationState())
            ->setJob($job)
            ->setLastNotifiedStatus('finished')
            ->setLastNotifiedAt(new \DateTimeImmutable('2026-04-01 00:00:00'));

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('persist');
        $entityManager->expects($this->once())->method('flush');

        $jobRepository = $this->createMock(JobRepository::class);
        $jobRepository->expects($this->once())
            ->method('findByStatuses')
            ->willReturn([$job]);

        $dispatcher = $this->createMock(JobStatusEmailDispatcherService::class);
        $dispatcher->expects($this->never())->method('queue');

        $stateRepository = $this->createMock(JobStatusNotificationStateRepository::class);
        $stateRepository->expects($this->once())
            ->method('findOneByJob')
            ->with($job)
            ->willReturn($existingState);

        $tester = $this->createCommandTester(new NotifyJobStatusChangesCommand(
            $entityManager,
            $jobRepository,
            $dispatcher,
            $stateRepository,
        ));

        $exitCode = $tester->execute([
            '--statuses' => 'finished',
            '--limit' => 10,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('queued=0', $tester->getDisplay());
        $this->assertStringContainsString('skipped_unchanged=1', $tester->getDisplay());
    }

    private function createCommandTester(NotifyJobStatusChangesCommand $command): CommandTester
    {
        $application = new Application();
        $application->addCommand($command);

        return new CommandTester($application->find('app:notify-job-status-changes'));
    }
}
