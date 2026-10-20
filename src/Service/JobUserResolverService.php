<?php

namespace App\Service;

use App\Auth\Entity\UserEntity;
use App\Auth\Repository\UserRepository;
use App\Auth\Service\GlobalRoleService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class JobUserResolverService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly GlobalRoleService $roleService,
        private readonly Security $security,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly UserRepository $userRepository,
    )
    {
    }

    public function resolve(FormInterface $form): UserEntity
    {
        // If the user is logged in (either private or public modes), return it
        $user = $this->security->getUser();
        if ($user instanceof UserEntity) {
            return $user;
        }

        // If the user is not logged in, the site is in public mode

        // Obtain the email from the form
        $email = $form->has('email') ? $form->get('email')->getData() : null;
        if (!$email) {
            throw new \LogicException('An email address is required for job submissions.');
        }

        // Check if a ghost (or real) user already exists with this email
        $existingUser = $this->userRepository->findOneBy(['email' => $email]);
        if ($existingUser) {
            return $existingUser;
        }

        $role = $this->roleService->getRole(\App\Auth\Roles::ROLE_USER);

        // Create a new ghost user
        $ghostUser = new UserEntity();
        $ghostUser->setEmail($email);
        $ghostUser->setFullName($email);
        $ghostUser->setGlobalRole($role);
        
        // Flag as a ghost user so the account can be "claimed" later through an email link
        $ghostUser->setIsPublicGhost(true); 

        // Set a secure random password so the account is locked until claimed
        $randomPassword = bin2hex(random_bytes(20));
        $ghostUser->setPassword($this->passwordHasher->hashPassword($ghostUser, $randomPassword));

        $this->entityManager->persist($ghostUser);
        
        // Do not flush here, since the job submission handler will flush the user and the job
        // together in one transaction

        return $ghostUser;
    }
}
