<?php

namespace App\Auth\Voter;

use App\Entity\Job;
use App\Auth\Entity\UserEntity;
use App\Auth\Roles;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class JobViewVoter extends Voter
{
    public const VIEW = 'JOB_VIEW';

    public function __construct(
        private readonly string $accessMode,
    )
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::VIEW && $subject instanceof Job;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        // If the mode is PUBLIC, always permit viewing of a job with only ID and UUID
        if ($this->accessMode === \App\Auth\AccessMode::PUBLIC) {
            return true;
        }

        $user = $token->getUser();
        if (!$user instanceof UserEntity) {
            return false;
        }

        // Super Admins see everything
        if (in_array(Roles::ROLE_SUPER_ADMIN, $user->getRoles())) {
            return true;
        }

        /** @var Job $job */
        $job = $subject;
        $jobOwner = $job->getUser();

        if (!$jobOwner) {
            return false;
        }

        // Check if the user is an org admin, and the job owner belongs to the same organization
        if (in_array(Roles::ROLE_ORG_ADMIN, $user->getRoles())) {
            $userOrg = $user->getPrimaryOrganization();
            $jobOrg = $jobOwner->getPrimaryOrganization();
            if ($userOrg !== null && $jobOrg !== null && $userOrg === $jobOrg) {
                return true;
            }
        }

        // Otherwise, the user is a normal user and return ownership
        return $jobOwner === $user;
    }
}
