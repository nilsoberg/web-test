<?php

namespace App\Auth\Service;

use App\Auth\Entity\UserEntity;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class AdminAccountCreatedEmailService
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly LoggerInterface $logger,
        private readonly string $fromEmail,
        private readonly string $fromName,
    ) {
    }

    public function send(UserEntity $user, string $plainPassword): void
    {
        $loginUrl = $this->urlGenerator->generate('auth_login', [], UrlGeneratorInterface::ABSOLUTE_URL);

        $email = (new TemplatedEmail())
            ->from(new Address($this->fromEmail, $this->fromName))
            ->to((string) $user->getEmail())
            ->subject('Your EFI Tools account has been created')
            ->htmlTemplate('auth/useradmin/account_created_email.html.twig')
            ->textTemplate('auth/useradmin/account_created_email.txt.twig')
            ->context([
                'fullName' => $user->getFullName(),
                'loginEmail' => $user->getEmail(),
                'plainPassword' => $plainPassword,
                'loginUrl' => $loginUrl,
                'fromName' => $this->fromName,
            ]);

        try {
            $this->mailer->send($email);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to send new-account email.', [
                'user_id' => $user->getId(),
                'email' => $user->getEmail(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
