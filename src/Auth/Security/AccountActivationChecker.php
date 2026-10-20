<?php

namespace App\Auth\Security;

use App\Auth\Entity\UserEntity;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class AccountActivationChecker implements UserCheckerInterface
{
    public function __construct(
        private readonly string $accessMode,
    )
    {
    }

    public function checkPreAuth(UserInterface $user): void
    {
        // If the site is in private mode then account verification is not applicable
        if ($this->accessMode === \App\Auth\AccessMode::PRIVATE) {
            return;
        }

        if (!$user instanceof UserEntity) {
            return;
        }

        // Prevent login if the account is not activated/verified
        if (!$user->isPublicVerified()) {
            // This exception will be caught by the firewall and displayed on the login form
            throw new CustomUserMessageAccountStatusException(
                'Your account has not been activated. Please check your email for the activation link.'
            );
        }
    }

    public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void
    {
    }
}
