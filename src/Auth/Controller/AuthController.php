<?php

/*
This controller will serve as a base to the Authentication system.
*/

namespace App\Auth\Controller;

use App\Attribute\RateLimit;
use App\Form\LoginForm;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class AuthController extends AbstractController
{
    #[Route('/login', name: 'auth_login')]
    #[RateLimit(limiter: 'auth_submission')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // Redirect if user is already authenticated
        if ($this->getUser()) {
             return $this->redirectToRoute('home');
        }
        
        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        $form = $this->createForm(LoginForm::class, [
            '_username' => $lastUsername,
        ]);

        return $this->render('auth/login/login.html.twig', [
            'loginForm' => $form->createView(),
            'error' => $error,
        ]);
    }

    #[Route('/logout', name: 'auth_logout')]
    #[RateLimit(limiter: 'auth_submission')]
    public function logout(): void
    {
    }
}
