<?php

namespace App\Service;

use App\Auth\Service\AdminAccountCreatedEmailService;
use App\Auth\Service\TemporaryPasswordGenerator;
use App\Auth\Entity\OrganizationEntity;
use App\Auth\Entity\Role;
use App\Auth\Entity\UserEntity;
use App\Auth\Entity\UserOrganizationRole;
use App\Auth\Roles;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserManagementService
{
    public function __construct(
        private readonly AdminAccountCreatedEmailService $accountCreatedEmailService,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        private readonly Security $security,
        private readonly TemporaryPasswordGenerator $temporaryPasswordGenerator,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly string $defaultUserTimezone,
    ) {
    }

    /**
     * Create a user account.  Called when the site is in either PRIVATE or PUBLIC mode and an
     * _admin_ user initiates the account creation request.  Sends an email to the user when the
     * account is created.
     * @param {UserEntity} $newUser - user object created by the controller and edited by the form
     * @param {bool} $isEditorSuperAdmin - true if the user starting the editing process is a
     *     super admin user; if false then the user takes on the organization of the iniating user
     */
    public function create(UserEntity $newUser, bool $isEditorSuperAdmin): void
    {
        $temporaryPassword = $this->temporaryPasswordGenerator->generate();

        try {
            // Ensure that the primary organization is the same organization if the current editor is
            // not a super admin
            if (!$isEditorSuperAdmin) {
                $currentUser = $this->security->getUser();
                $originalOrg = $currentUser->getPrimaryOrganization();
                $newUser->setPrimaryOrganization($originalOrg);
            }

            $this->createNewUser($newUser, $temporaryPassword);
            $this->accountCreatedEmailService->send($newUser, $temporaryPassword);
        } catch (\Exception $e) {
            // Catch, log, and rethrow database exceptions
            $this->logger->error('Database error encountered when creating user', [
                'full_name' => $newUser->getFullName(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Apply user account edits, initiated by a super or org admin in a PRIVATE mode installation.
     * @param {UserEntity} $user - user being edited in the form
     * @param {?string} $plainPassword - if this is set, the password is replaced, otherwise it
     *     stays the same
     * @param {bool} $isEditorSuperAdmin - true if the user is a superadmin, and permits editing
     *     of the organization; if false, the primary organization cannot be changed
     */
    public function edit(UserEntity $user, ?string $plainPassword, bool $isEditorSuperAdmin): void
    {
        // Optionally update password if a new one was entered
        if (!empty($plainPassword)) {
            $user->setPassword(
                $this->passwordHasher->hashPassword($user, $plainPassword)
            );
        }

        // Ensure that the primary organization is the same organization if the current editor is
        // not a super admin
        if (!$isEditorSuperAdmin) {
            $currentUser = $this->security->getUser();
            $originalOrg = $currentUser->getPrimaryOrganization();
            $user->setPrimaryOrganization($originalOrg);
        } else {
            // If the editor user is a super admin, then if the user being edited is elevated to
            // super admin status, we need to set the primary organization to be null
            $superAdminRole = $this->entityManager->getRepository(Role::class)->findOneBy(['name' => Roles::ROLE_SUPER_ADMIN]);
            if ($user->getGlobalRole() === $superAdminRole) {
                $user->setPrimaryOrganization(null);
            }
        }

        $this->entityManager->flush();
    }

    private function createNewUser(UserEntity $newUser, string $plainPassword): void
    {
        if ($newUser->getTimezone() === null) {
            $newUser->setTimezone($this->defaultUserTimezone);
        }

        $hashedPassword = $this->passwordHasher->hashPassword($newUser, $plainPassword);
        $newUser->setPassword($hashedPassword);
        $newUser->setMustChangePassword(true);

        $this->entityManager->persist($newUser);
        $this->entityManager->flush();
    }
}
