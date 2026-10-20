<?php

namespace App\Auth\Voter;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class FeatureVoter extends Voter
{
    public const FEATURE_ACTIVE = 'FEATURE_ACTIVE';

    public function __construct(
        private readonly array $disabledFeatures = []
    )
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::FEATURE_ACTIVE && is_string($subject);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        // If the feature key is not present we enable it.
        // disabledFeatures true means it's disabled
        $val = (!isset($this->disabledFeatures[$subject]) || !$this->disabledFeatures[$subject]);
        return $val;
    }
}
