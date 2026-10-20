<?php

namespace App\Auth\Controller;

use App\Attribute\RateLimit;
use App\Auth\Entity\UserEntity;
use App\Auth\Repository\UserRepository;
use App\Auth\Service\PublicUserAccountService;
use App\Dto\PublicUserCreationData;
use App\Exception\PublicUserCreationException;
use App\Form\PublicAccountChangePasswordForm;
use App\Form\PublicUserCreationForm;
use App\Form\PublicUserProfileForm;
use App\Service\StateChangeFormValidationService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/p/auth')]
class PublicUserAccountController extends AbstractController
{
    public const PUBLIC_USER_DEACTIVATE_PREFIX = 'public_user_deactivate';

    public function __construct(
        private readonly PublicUserAccountService $userService,
        private readonly StateChangeFormValidationService $stateValidationService,
        private readonly string $defaultUserTimezone,
    )
    {
    }

    #[Route('/new-account', name: 'new_public_account', methods: ['GET', 'POST'])]
    #[RateLimit(limiter: 'auth_submission')]
    public function createNewPublicAccount(
        Request $request,
    ): Response
    {
        $user = $this->getUser();
        if ($user instanceof UserEntity) {
            throw $this->createAccessDeniedException('Login state error.');
        }

        $newUser = new PublicUserCreationData();
        $newUser->timezone = $this->defaultUserTimezone;
        $form = $this->createForm(PublicUserCreationForm::class, $newUser);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('passwordFields')->get('plainPassword')->getData();

            try {
                $this->userService->createPublicAccount($newUser, $plainPassword);
                $this->addFlash('success', 'Account has been created successfully.');
                return $this->redirectToRoute('public_account_activation_required');
            } catch (PublicUserCreationException $e) {
                // It's OK to use getMessage because we are limiting what is displayed in the custom exception
                $this->addFlash('danger', 'Unable to create account: ' . $e->getMessage());
                return $this->redirectToRoute('new_public_account');
            }
        }

        return $this->render('auth/public/account_creation.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/activation-required', name: 'public_account_activation_required', methods: ['GET'])]
    #[RateLimit(limiter: 'auth_submission')]
    public function notifyActivationRequired(
    ): Response
    {
        return $this->render('auth/public/activation_required.html.twig', []);
    }

    #[Route('/activate-account', name: 'public_verify_email')]
    #[RateLimit(limiter: 'auth_submission')]
    public function activateAccount(
        Request $request, 
    ): Response
    {
        $idStr = $request->query->get('id');
        if ($idStr === null || !is_numeric($idStr)) {
            return $this->redirectToRoute('new_public_account');
        }

        $id = intval($idStr);

        $result = $this->userService->activateAccount($id, $request);

        if (!$result) {
            $this->addFlash('error', 'Unable to activate account: invalid data');
            return $this->redirectToRoute('new_public_account');
        }

        return $this->redirectToRoute('auth_login');
    }

    #[Route('/edit-profile', name: 'public_edit_user_profile', methods: ['GET', 'POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[RateLimit(limiter: 'auth_submission')]
    public function editUserProfile(
        Request $request,
    ): Response
    {
        $user = $this->getUser();

        $form = $this->createForm(PublicUserProfileForm::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->userService->editUserProfile($user);
            $this->addFlash('success', 'Profile updated successfully.');
            return $this->redirectToRoute('public_edit_user_profile');
        }

        $deactivateForm = $this->stateValidationService->createStateChangeForm(
            'public_deactivate_account',
            ['id' => $user->getId()],
            self::PUBLIC_USER_DEACTIVATE_PREFIX . '_' . $user->getId()
        );

        return $this->render('auth/public/user-profile.html.twig', [
            'admin_title' => 'User Profile',
            'user' => $user,
            'form' => $form->createView(),
            'deactivate_form' => $deactivateForm->createView(),
        ]);
    }

    #[Route('/deactivate-account', name: 'public_deactivate_account', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[RateLimit(limiter: 'auth_submission')]
    public function deactivateUser(
        Request $request,
    ): Response
    {
        $user = $this->getUser();

        $csrfTokenId = self::PUBLIC_USER_DEACTIVATE_PREFIX . '_' . $user->getId();

        // First verify that the request was valid (e.g. CSRF token validation by Symfony)
        if (!$this->stateValidationService->isStateChangeValid($request, $csrfTokenId)) {
            throw $this->createAccessDeniedException('State error.' . $csrfTokenId);
        }

        $this->userService->deactivateUser($user);

        $this->addFlash('success', 'Account has been permanently deactivated.');

        return $this->redirectToRoute('home');
    }

    #[Route('/change-password', name: 'public_account_change_password', methods: ['GET', 'POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[RateLimit(limiter: 'auth_submission')]
    public function changePassword(
        Request $request,
    ): Response
    {
        $user = $this->getUser();

        $form = $this->createForm(PublicAccountChangePasswordForm::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $currentPassword = $form->get('currentPassword')->getData();
            $newPlainPassword = $form->get('passwordFields')->get('plainPassword')->getData();

            if (!$this->userService->changePassword($user, $currentPassword, $newPlainPassword)) {
                $form->get('currentPassword')->addError(new FormError('Current password is incorrect.'));
            } else {
                $this->addFlash('success', 'Your password has been updated successfully.');
                return $this->redirectToRoute('home');
            }
        }

        // Share front-end code between public and private modes since the password logic is
        // complicated and the forms are identical.
        return $this->render('auth/public/change-password.html.twig', [
            'admin_title' => 'Change Password',
            'changePasswordForm' => $form->createView(),
        ]);
    }
}
