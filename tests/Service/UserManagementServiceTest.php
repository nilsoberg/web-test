<?php

namespace App\Tests\Service;

use App\Auth\Entity\UserEntity;
use App\Auth\Service\AdminAccountCreatedEmailService;
use App\Auth\Service\GlobalRoleService;
use App\Auth\Service\TemporaryPasswordGenerator;
use App\Service\UserManagementService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserManagementServiceTest extends TestCase
{
    private $entityManager;
    private $passwordHasher;
    private $security;
    private $emailService;
    private $logger;
    private $temporaryPasswordGenerator;
    private $userService;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $this->security = $this->createMock(Security::class);
        $this->emailService = $this->createMock(AdminAccountCreatedEmailService::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->temporaryPasswordGenerator = $this->createMock(TemporaryPasswordGenerator::class);

        $this->userService = new UserManagementService(
            $this->emailService,
            $this->entityManager,
            $this->logger,
            $this->security,
            $this->temporaryPasswordGenerator,
            $this->passwordHasher,
            'Asia/Singapore',
        );

    }

    public function testCreatePersistsUserAndSendsAccountCreatedEmail(): void
    {
        $user = new UserEntity();
        $user->setFullName('New User');
        $user->setEmail('new.user@example.com');

        $plainPassword = 'TempPass#2026!Ab';
        $hashedPassword = 'hashed-password';

        $this->temporaryPasswordGenerator->expects($this->once())
            ->method('generate')
            ->willReturn($plainPassword);

        $this->passwordHasher->expects($this->once())
            ->method('hashPassword')
            ->with($user, $plainPassword)
            ->willReturn($hashedPassword);

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($user);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->emailService->expects($this->once())
            ->method('send')
            ->with($user, $plainPassword);

        $this->userService->create($user, true);

        $this->assertSame($hashedPassword, $user->getPassword());
        $this->assertTrue($user->getMustChangePassword());
    }

    public function testCreateGeneratesPasswordAndPersistsUser(): void
    {
        $user = new UserEntity();
        $user->setFullName('New User');
        $user->setEmail('new.user@example.com');
        $generatedPassword = 'AnyTemp#2026!Ab';

        $this->temporaryPasswordGenerator->expects($this->once())
            ->method('generate')
            ->willReturn($generatedPassword);

        $this->passwordHasher->expects($this->once())
            ->method('hashPassword')
            ->with($user, $generatedPassword)
            ->willReturn('hashed-value');

        $this->entityManager->expects($this->once())
            ->method('persist');

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->emailService->expects($this->once())
            ->method('send');

        $this->userService->create($user, true);
    }
}
