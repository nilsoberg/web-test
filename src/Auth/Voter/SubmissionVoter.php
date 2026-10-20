<?php

namespace App\Auth\Voter;

use App\Auth\Roles;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

class SubmissionVoter extends Voter
{
    public const JOB_SUBMISSION = 'JOB_SUBMISSION';

    public function __construct(
        private readonly Security $security,
        private readonly string $accessMode,
    )
    {
    }

    protected function supports(string $attribute, $subject): bool
    {
        return in_array($attribute, [self::JOB_SUBMISSION]);
    }

    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        if ($this->accessMode === \App\Auth\AccessMode::PUBLIC) {
            return true;
        }

        $user = $token->getUser();

        if (!$user instanceof UserInterface) {
            return false;
        }

        switch ($attribute) {
            case self::JOB_SUBMISSION:
                if ($this->security->isGranted(Roles::ROLE_SUPER_ADMIN) ||
                    $this->security->isGranted(Roles::ROLE_ORG_ADMIN) ||
                    $this->security->isGranted(Roles::ROLE_DATA_ADMIN) ||
                    $this->security->isGranted(Roles::ROLE_ORG_MEMBER)
                    ) {
                    return true;
                }
                break;
        }

        return false;
    }
}
