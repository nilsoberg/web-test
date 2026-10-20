<?php

namespace App\Tests\Auth\Service;

use App\Auth\Repository\UserRepository;
use App\Auth\Service\GlobalRoleService;
use App\Auth\Service\PublicAccountActivationService;
use App\Auth\Service\PublicUserAccountService;
use App\Dto\PublicUserCreationData;
use App\Tests\BaseServiceTestCase;
use App\Tests\Factory\UserEntityFactory;
use App\Tests\Traits\EntityIdSetterTrait;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

class PublicUserAccountServiceTest extends BaseServiceTestCase
{
    use EntityIdSetterTrait;

    public function test_deactivate_user_anonymizes_data(): void
    {
        $user = UserEntityFactory::createOne()->_real();
        $this->setEntityId($user, 123);
        $user->setFullName('John Doe');
        $user->setEmail('john@example.com');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('flush');

        $security = $this->createMock(Security::class);
        $security->expects($this->once())->method('logout')->with(false);

        $service = new PublicUserAccountService(
            $entityManager,
            $this->createMock(GlobalRoleService::class),
            $this->createMock(LoggerInterface::class),
            $this->createMock(PublicAccountActivationService::class),
            $security,
            $this->createMock(UserPasswordHasherInterface::class),
            $this->createMock(UserRepository::class),
            $this->createMock(VerifyEmailHelperInterface::class),
            'UTC'
        );

        $service->deactivateUser($user);

        $this->assertEquals('Deactivated User Account', $user->getFullName());
        $this->assertStringContainsString('deactivated-user-123', $user->getEmail());
        $this->assertTrue($user->isDeleted());
    }
}
