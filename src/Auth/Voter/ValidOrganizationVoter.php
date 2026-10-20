<?php

namespace App\Auth\Voter;

use App\Auth\Entity\OrganizationEntity;
use App\Auth\Entity\UserEntity;
use App\Auth\Roles;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

// Checks if a user belongs to any organization.  A org admin user must belong to an
// organization in order to do any administrative tasks.
class ValidOrganizationVoter extends Voter
{
    const IS_VALID_ORG = 'IS_VALID_ORG';

    protected function supports(string $attribute, $subject): bool
    {
        // This voter only applies to the 'IS_VALID_ORG' attribute
        // and only for OrganizationEntity objects.
        return $attribute === self::IS_VALID_ORG
            && ($subject instanceof OrganizationEntity || $subject === null);
    }

    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        // If the user is not logged in, deny access
        if (!$user instanceof UserEntity) {
            return false;
        }

        $role = $user->getGlobalRole();
        $isSuperAdmin = $role && $role->getName() === Roles::ROLE_SUPER_ADMIN;

        return $isSuperAdmin || $user->getPrimaryOrganization() !== null;
    }
}
