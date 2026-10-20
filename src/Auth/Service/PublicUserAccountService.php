<?php

namespace App\Auth\Service;

use App\Auth\Entity\UserEntity;
use App\Auth\Repository\UserRepository;
use App\Auth\Service\GlobalRoleService;
use App\Auth\Service\PublicAccountActivationService;
use App\Dto\PublicUserCreationData;
use App\Exception\PublicUserCreationException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

class PublicUserAccountService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly GlobalRoleService $roleService,
        private readonly LoggerInterface $logger,
        private readonly PublicAccountActivationService $activationService,
        private readonly Security $security,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly UserRepository $userRepository, 
        private readonly VerifyEmailHelperInterface $verifyEmailHelper,
        private readonly string $defaultUserTimezone,
    )
    {
    }

    /**
     * Create a user account.  Called when the site is in PUBLIC mode and an unprivileged user
     * initiates the account creation request.  Sends an email to the user when the account is
     * created.
     * @param {PublicUserCreationData} $userData - DTO containing new user account data from the form
     * @exception {PublicUserCreationException} throws an exception when creation failed; it is
     *     safe to call getMessage() on this exception and pass that to a flash message
     */
    public function createPublicAccount(PublicUserCreationData $userData, string $plainPassword): void
    {
        $existingUser = $this->userRepository->findOneBy(['email' => $userData->email]);

        $timezone = $userData->timezone ?? $this->defaultUserTimezone;

        // This user already exists as a ghost user
        if ($existingUser) {
            if ($existingUser->isPublicGhost()) {
                $targetUser = $existingUser;

            // Email address already exists in the system
            } else {
                throw new PublicUserCreationException("This email address is already in use");
            }

        // Brand new user, create the UserEntity
        } else {
            $targetUser = new UserEntity();
            $targetUser->setEmail($userData->email);
            $role = $this->roleService->getRole(\App\Auth\Roles::ROLE_USER);
            $targetUser->setGlobalRole($role);
            $targetUser->setPrimaryOrganization(null);
        }

        try {
            $targetUser->setFullName($userData->fullName);
            $targetUser->setTimezone($timezone);
            $targetUser->setPassword($this->passwordHasher->hashPassword($targetUser, $plainPassword));

            $this->entityManager->persist($targetUser);
            $this->entityManager->flush();
        } catch (\Exception $e) {
            $this->logger->error('Failed to create public user data.', [
                'user_id' => $targetUser->getId(),
                'email' => $targetUser->getEmail(),
                'error' => $e->getMessage(),
            ]);
            throw new PublicUserCreationException("Invalid user data");
        }

        try {
            $this->activationService->sendActivationEmail($targetUser);
        } catch (\Exception $e) {
            $this->logger->error('Failed to send public new-account email.', [
                'user_id' => $targetUser->getId(),
                'email' => $targetUser->getEmail(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Deactivate a user by anonymizing their information.
     * @param {UserEntity} $user - user entity being deactivated
     */
    public function deactivateUser(UserEntity $user): void
    {
        $randomHash = bin2hex(random_bytes(8));
        $anonymizedEmail = sprintf('deactivated-user-%d-%s@example.com', $user->getId(), $randomHash);

        $user->setFullName('Deactivated User Account');
        $user->setEmail($anonymizedEmail);
        $user->setPrimaryOrganization(null);
        $user->setIsDeleted(true);
        $this->entityManager->flush();

        $this->security->logout(false);
    }

    /**
     * Edit the user profile by allowing updates of timezone and full name..
     * @param {UserEntity} $user - user entity being edited
     */
    public function editUserProfile(UserEntity $user): void
    {
        if ($user->getTimezone() === null) {
            $user->setTimezone($this->defaultUserTimezone);
        }
        $this->entityManager->flush();
    }

    /**
     * Activate an account for the given activation parameters.  Verifies that the activation
     * request is valid (e.g. correct user ID) first.  Sends an activation confirmation email
     * upon update.
     * @param {int} $id - user ID
     * @param {Request} $request - request object from controller used to obtain activation
     *      information
     * @return {bool} true if activation succeeded, false otherwise
     */
    public function activateAccount(int $id, Request $request): bool
    {
        $user = $this->userRepository->find($id);
        if ($user === null) {
            $this->logger->error('No user found when attempting to activate.', [
                'request_user_id' => $id,
            ]);
            return false;
        }

        try {
            $this->verifyEmailHelper->validateEmailConfirmationFromRequest($request, $user->getId(), $user->getEmail());
        } catch (VerifyEmailExceptionInterface $e) {
            // The link is invalid, expired, or tampered with
            $this->logger->error('Failed to send public new-account email.', [
                'user_id' => $user->getId(),
                'email' => $user->getEmail(),
                'error' => $e->getMessage(),
            ]);
            return false;
        }

        $user->setIsPublicVerified(true);
        $user->setIsPublicGhost(false);

        $this->entityManager->flush();

        return true;
    }

    /**
     * Change the given user's password.
     * @param {UserEntity} $user - user entity being edited
     * @param {string} $oldPassword - old password provided by form, for confirmation
     * @param {string} $newPassword - new password provided by form
     * @return {bool} true if old password matched and new password was updated
     */
    public function changePassword(UserEntity $user, string $oldPassword, string $newPassword): bool
    {
        if (!$this->passwordHasher->isPasswordValid($user, $oldPassword)) {
            return false;
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $newPassword));
        $user->setMustChangePassword(false);
        $this->entityManager->flush();

        return true;
    }
}
