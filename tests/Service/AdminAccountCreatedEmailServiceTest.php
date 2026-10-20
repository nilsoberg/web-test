<?php

namespace App\Tests\Service;

use App\Auth\Entity\UserEntity;
use App\Auth\Service\AdminAccountCreatedEmailService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class AdminAccountCreatedEmailServiceTest extends TestCase
{
    public function testSendBuildsAndSendsEmailWithoutCcOrBcc(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $service = new AdminAccountCreatedEmailService(
            $mailer,
            $urlGenerator,
            $logger,
            'fighterfish.2013@gmail.com',
            'EFI Tools'
        );

        $user = new UserEntity();
        $user->setFullName('Test User');
        $user->setEmail('new.user@example.com');

        $urlGenerator->expects($this->once())
            ->method('generate')
            ->with('auth_login', [], UrlGeneratorInterface::ABSOLUTE_URL)
            ->willReturn('http://localhost:8080/login');

        $logger->expects($this->never())
            ->method('error');

        $mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function ($message): bool {
                $this->assertInstanceOf(TemplatedEmail::class, $message);

                /** @var TemplatedEmail $message */
                $this->assertSame('Your EFI Tools account has been created', $message->getSubject());
                $this->assertSame('fighterfish.2013@gmail.com', $message->getFrom()[0]->getAddress());
                $this->assertSame('EFI Tools', $message->getFrom()[0]->getName());
                $this->assertSame('new.user@example.com', $message->getTo()[0]->getAddress());
                $this->assertCount(0, $message->getCc());
                $this->assertCount(0, $message->getBcc());
                $this->assertSame('auth/useradmin/account_created_email.html.twig', $message->getHtmlTemplate());
                $this->assertSame('auth/useradmin/account_created_email.txt.twig', $message->getTextTemplate());
                $this->assertSame([
                    'fullName' => 'Test User',
                    'loginEmail' => 'new.user@example.com',
                    'plainPassword' => 'EfiTools#2026!Ab',
                    'loginUrl' => 'http://localhost:8080/login',
                    'fromName' => 'EFI Tools',
                ], $message->getContext());

                return true;
            }));

        $service->send($user, 'EfiTools#2026!Ab');
    }

    public function testSendLogsErrorWhenMailerThrows(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $service = new AdminAccountCreatedEmailService(
            $mailer,
            $urlGenerator,
            $logger,
            'fighterfish.2013@gmail.com',
            'EFI Tools'
        );

        $user = new UserEntity();
        $user->setFullName('Test User');
        $user->setEmail('new.user@example.com');

        $urlGenerator->expects($this->once())
            ->method('generate')
            ->willReturn('http://localhost:8080/login');

        $mailer->expects($this->once())
            ->method('send')
            ->willThrowException(new \RuntimeException('smtp failed'));

        $logger->expects($this->once())
            ->method('error')
            ->with(
                'Failed to send new-account email.',
                $this->callback(function (array $context): bool {
                    return isset($context['email']) && $context['email'] === 'new.user@example.com';
                })
            );

        $service->send($user, 'EfiTools#2026!Ab');
    }
}
