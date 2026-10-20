<?php

// This function handles the behaviour upon a successiful login.

namespace App\Auth\Security;

use App\Auth\Entity\UserEntity;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

class LoginSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function __construct(private readonly RouterInterface $router)
    {
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): RedirectResponse
    {
        $user = $token->getUser();
        if ($user instanceof UserEntity && $user->getMustChangePassword()) {
            return new RedirectResponse($this->router->generate('account_change_password'));
        }

        $response = new RedirectResponse($this->router->generate('home'));
        return $response;
    }

}
