<?php

namespace App\Tests\MessageHandler;

use App\Message\SubmissionEmailMessage;
use App\MessageHandler\SubmissionEmailMessageHandler;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class SubmissionEmailMessageHandlerTest extends TestCase
{
    public function testHandlerSendsEmailWithConfiguredSenderAndOptionalBcc(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Email $email): bool {
                $this->assertSame('from@example.com', $email->getFrom()[0]->getAddress());
                $this->assertSame('EFI Tools', $email->getFrom()[0]->getName());
                $this->assertSame('user@example.com', $email->getTo()[0]->getAddress());
                $this->assertSame('audit@example.com', $email->getBcc()[0]->getAddress());
                $this->assertSame('Test Subject', $email->getSubject());

                return true;
            }));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('warning');
        $logger->expects($this->never())->method('error');

        $handler = new SubmissionEmailMessageHandler(
            $mailer,
            $logger,
            'from@example.com',
            'EFI Tools',
            'audit@example.com',
            false,
        );

        $message = new SubmissionEmailMessage(
            recipientEmail: 'user@example.com',
            subject: 'Test Subject',
            context: ['body' => 'Body'],
            messagePurpose: 'email_started',
        );

        $handler($message);
    }

    public function testHandlerSkipsInvalidBccConfiguration(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Email $email): bool {
                $this->assertCount(0, $email->getBcc());

                return true;
            }));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with(
                'Skipping submission email BCC because configured address is invalid.',
                $this->callback(fn(array $context): bool => $context['submission_bcc'] === 'invalid-bcc')
            );
        $logger->expects($this->never())->method('error');

        $handler = new SubmissionEmailMessageHandler(
            $mailer,
            $logger,
            'from@example.com',
            'EFI Tools',
            'invalid-bcc',
            false,
        );

        $message = new SubmissionEmailMessage(
            recipientEmail: 'user@example.com',
            subject: 'Test Subject',
            context: ['body' => 'Body'],
            messagePurpose: 'email_started',
        );

        $handler($message);
    }

    public function testHandlerLogsErrorWhenSendFails(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())
            ->method('send')
            ->willThrowException(new \RuntimeException('smtp failed'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('warning');
        $logger->expects($this->once())
            ->method('error')
            ->with(
                'Failed to send submission email from async handler.',
                $this->callback(function (array $context): bool {
                    return $context['email'] === 'user@example.com'
                        && $context['subject'] === 'Test Subject'
                        && str_contains($context['error'], 'smtp failed');
                })
            );

        $handler = new SubmissionEmailMessageHandler(
            $mailer,
            $logger,
            'from@example.com',
            'EFI Tools',
            '',
            false,
        );

        $message = new SubmissionEmailMessage(
            recipientEmail: 'user@example.com',
            subject: 'Test Subject',
            context: ['body' => 'Body'],
            messagePurpose: 'email_started',
        );

        $handler($message);
    }
}

