<?php

namespace App\Auth\Voter;

use App\Auth\Entity\OrganizationEntity;
use App\Auth\Entity\UserEntity;
use App\Auth\Roles;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class UserVoter extends Voter
{
    public const EDIT = 'USER_EDIT';
    public const CREATE = 'USER_CREATE';
    public const VIEW_ADMIN = 'USER_VIEW_ADMIN'; // Permission to view the user admin page

    public function __construct(
        private Security $security
    )
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        // This voter applies to viewing the admin or editing a UserEntity
        return in_array($attribute, [self::EDIT, self::VIEW_ADMIN, self::CREATE])
            && ($subject instanceof UserEntity || $subject === null);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $currentUser = $token->getUser();

        if (!$currentUser instanceof UserEntity) {
            // The user must be logged in; if not, deny access
            return false;
        }

        // A Super Admin can do anything
        if ($this->security->isGranted(Roles::ROLE_SUPER_ADMIN)) {
            return true;
        }

        if (!$this->security->isGranted(ValidOrganizationVoter::IS_VALID_ORG)) {
            return false;
        }

        // Org Admins have specific rules
        if ($this->security->isGranted(Roles::ROLE_ORG_ADMIN)) {
            switch ($attribute) {
                case self::VIEW_ADMIN:
                    // An org admin is allowed to see the user admin list
                    return true;

                case self::CREATE:
                    // An org admin is allowed to create new users
                    return true;

                case self::EDIT:
                    // An org admin can edit users that belong to their org
                    $targetUser = $subject;
                    return $this->canOrgAdminEditUser($currentUser, $targetUser);
            }
        }

        return false;
    }

    private function canOrgAdminEditUser(UserEntity $admin, UserEntity $targetUser): bool
    {
        // An organization admin must belong to an organization to edit users; sanity check
        $adminOrganization = $admin->getPrimaryOrganization();
        if (!$adminOrganization) {
            return false;
        }

        // Check if the user's primary organization is the same as the admin's primary organization
        // which automatically means permission is granted
        if ($targetUser->getPrimaryOrganization() === $adminOrganization) {
            // Check if the primary organization is the same as the org admin
            return true;
        }

        // An organization admin shouldn't be able to edit the user if the user's primary
        // organization is not the same as the admin's organization, even if the user shares some
        // roles with the admin's organization.

        return false;
    }
}
