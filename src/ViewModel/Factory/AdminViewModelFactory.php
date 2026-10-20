<?php

namespace App\ViewModel\Factory;

use App\Auth\Entity\OrganizationEntity;
use App\Auth\Entity\UserEntity;
use App\Auth\Roles;
use App\Config\Constants;
use App\Service\StateChangeFormValidationService;
use App\ViewModel\OrganizationAdminViewModel;
use App\ViewModel\UserAdminViewModel;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

class AdminViewModelFactory
{
    private array $roleDisplayNames = [
        Roles::ROLE_DATA_ADMIN => 'Data Admin',
        Roles::ROLE_ORG_ADMIN => 'Org Admin',
        Roles::ROLE_ORG_MEMBER => 'Org Member',
        Roles::ROLE_SUPER_ADMIN => 'Superadmin',
        Roles::ROLE_USER => Roles::ROLE_USER_DISPLAY_NAME,
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
        private readonly StateChangeFormValidationService $stateValidationService,
    )
    {
    }

    public function createOrganizationViewModel(): OrganizationAdminViewModel
    {
        $orgs = $this->entityManager->getRepository(OrganizationEntity::class)->findAll();

        $data = [];
        foreach ($orgs as $org) {
            $deleteForm = $this->stateValidationService->createStateChangeForm(
                'superadmin_delete_org',
                ['id' => $org->getId()],
                'superadmin_delete_org_' . $org->getId()
            );
            $restoreForm = $this->stateValidationService->createStateChangeForm(
                'superadmin_restore_org',
                ['id' => $org->getId()],
                'superadmin_restore_org_' . $org->getId()
            );

            $orgData = [
                'id' => $org->getId(),
                'name' => $org->getName(),
                'isDeleted' => $org->isDeleted(),
                'isProtected' => $org->isProtected(),
                'deleteForm' => $deleteForm->createView(),
                'restoreForm' => $restoreForm->createView(),
            ];

            $admins = [];
            foreach ($org->getUsers() as $user) {
                if ($user->getGlobalRole()->getName() === Roles::ROLE_ORG_ADMIN) {
                    $admins[] = [
                        'name' => $user->getFullName(),
                        'email' => $user->getEmail(),
                    ];
                }
            }

            $orgData['admins'] = $admins;
            $data[] = $orgData;
        }

        return new OrganizationAdminViewModel(organizations: $data);
    }

    public function createUserViewModel(): UserAdminViewModel
    {
		$users = $this->getUsersByScope();

        $userData = [];
        foreach ($users as $user) {
            $primaryOrgName = '';
			$globalRole = '';
            if ($user->getGlobalRole()->getName() === Roles::ROLE_SUPER_ADMIN) {
                $primaryOrgName = $this->formatRole(Roles::ROLE_SUPER_ADMIN);
            } else {
                $primaryOrgName = $user->getPrimaryOrganization()?->getName() ?? 'None';
				$globalRole = $this->formatRole($user->getGlobalRole()->getName());
            }

            $deleteForm = $this->stateValidationService->createStateChangeForm(
                'useradmin_delete_user',
                ['id' => $user->getId()],
                'useradmin_delete_user_' . $user->getId()
            );
            $restoreForm = $this->stateValidationService->createStateChangeForm(
                'useradmin_restore_user',
                ['id' => $user->getId()],
                'useradmin_restore_user_' . $user->getId()
            );

            $userData[] = [
                'id' => $user->getId(),
                'full_name' => $user->getFullName(),
                'email' => $user->getEmail(),
                'is_deleted' => $user->isDeleted(),
                'role' => $globalRole,
				'primary_org' => $primaryOrgName,
                'delete_form' => $deleteForm->createView(),
                'restore_form' => $restoreForm->createView(),
            ];
        }

        $isSuperAdmin = $this->security->isGranted(Roles::ROLE_SUPER_ADMIN);
        $vm = new UserAdminViewModel(users: $userData, isSuperAdmin: $isSuperAdmin);

        return $vm;
    }

    private function getUsersByScope(): array
    {
        $currentUser = $this->security->getUser();
        $isSuperAdmin = $this->security->isGranted(Roles::ROLE_SUPER_ADMIN);
        $userRepository = $this->entityManager->getRepository(UserEntity::class);

        $usersToDisplay = [];
        if ($isSuperAdmin) {
            // Super admin sees all users
            $usersToDisplay = $userRepository->findAll();
        } elseif ($this->security->isGranted(Roles::ROLE_ORG_ADMIN)) {
            // Org admin only sees users from their own organization
            $userOrg = $currentUser->getPrimaryOrganization();
            if ($userOrg) {
                $usersToDisplay = $userRepository->findByOrganization($userOrg);
            }
        }

        return $usersToDisplay;
    }

    private function formatRole(?string $roleName): string
    {
        if ($roleName && isset($this->roleDisplayNames[$roleName])) {
            return $this->roleDisplayNames[$roleName];
        } else {
            return Roles::ROLE_USER_DISPLAY_NAME;
        }
    }
}
