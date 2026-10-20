<?php

namespace App\MessageHandler;

use App\Email\EmailEventTag;
use App\Message\SubmissionEmailMessage;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

#[AsMessageHandler]
class SubmissionEmailMessageHandler
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
        private readonly string $fromEmail,
        private readonly string $fromName,
        private readonly ?string $submissionBcc,
        private readonly bool $useHtmlEmail, // Reserved for future use
    )
    {
    }

    public function __invoke(SubmissionEmailMessage $message): void
    {
        $baseTemplate = match($message->messagePurpose) {
            EmailEventTag::SUBMITTED => 'email/submitted',
            EmailEventTag::STARTED => 'email/started',
            EmailEventTag::FINISHED => 'email/finished',
            EmailEventTag::FAILED => 'email/failed',
            default => 'email/generic'
        };

        $email = (new TemplatedEmail())
            ->from(new Address($this->fromEmail, $this->fromName))
            ->to($message->recipientEmail)
            ->subject($message->subject)
            ->textTemplate($baseTemplate . '.txt.twig')
            ->context($message->context);

        // Add bcc: to the email if it is configured
        $bcc = trim((string) ($this->submissionBcc ?? ''));
        if ($bcc !== '' && filter_var($bcc, FILTER_VALIDATE_EMAIL)) {
            $email->bcc($bcc);
        } else if ($bcc !== '') {
            $this->logger->warning('Skipping submission email BCC because configured address is invalid.', [
                'submission_bcc' => $bcc,
            ]);
        }

        try {
            $this->mailer->send($email);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to send submission email from async handler.', [
                'email' => $message->recipientEmail,
                'subject' => $message->subject,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

