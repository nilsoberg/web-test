<?php

namespace App\Tests\Service;

use App\Auth\Entity\UserEntity;
use App\Auth\Repository\UserRepository;
use App\Auth\Service\GlobalRoleService;
use App\Service\JobUserResolverService;
use App\Tests\BaseServiceTestCase;
use App\Tests\Factory\UserEntityFactory;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class JobUserResolverServiceTest extends BaseServiceTestCase
{
    public function test_resolves_to_existing_logged_in_user(): void
    {
        $user = UserEntityFactory::createOne();
        
        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);

        $service = new JobUserResolverService(
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(GlobalRoleService::class),
            $security,
            $this->createMock(UserPasswordHasherInterface::class),
            $this->createMock(UserRepository::class)
        );

        $form = $this->createMock(FormInterface::class);

        $resolvedUser = $service->resolve($form);
        $this->assertSame($user, $resolvedUser);
    }

    public function test_creates_ghost_user_when_not_logged_in(): void
    {
        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn(null); // Not logged in

        $emailField = $this->createMock(FormInterface::class);
        $emailField->method('getData')->willReturn('ghost@example.com');

        $form = $this->createMock(FormInterface::class);
        $form->method('has')->with('email')->willReturn(true);
        $form->method('get')->with('email')->willReturn($emailField);

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->method('findOneBy')->willReturn(null); // Ghost doesn't exist yet

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('persist')->with($this->isInstanceOf(UserEntity::class));

        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $passwordHasher->method('hashPassword')->willReturn('hashed_password');

        $service = new JobUserResolverService(
            $entityManager,
            $this->createMock(GlobalRoleService::class),
            $security,
            $passwordHasher,
            $userRepository
        );

        $resolvedUser = $service->resolve($form);

        $this->assertInstanceOf(UserEntity::class, $resolvedUser);
        $this->assertEquals('ghost@example.com', $resolvedUser->getEmail());
        $this->assertTrue($resolvedUser->isPublicGhost());
    }
}
