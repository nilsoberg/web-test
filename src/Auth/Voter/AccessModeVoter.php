<?php

namespace App\Auth\Voter;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class AccessModeVoter extends Voter
{
    public function __construct(
        private readonly string $accessMode,
    )
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === 'IS_PUBLIC_MODE';
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        return $this->accessMode === \App\Auth\AccessMode::PUBLIC;
    }
}
