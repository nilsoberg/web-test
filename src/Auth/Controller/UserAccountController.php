<?php

namespace App\Auth\Controller;

use App\Auth\Entity\UserEntity;
use App\Auth\Roles;
use App\Form\AccountChangePasswordForm;
use App\Form\UserProfileForm;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/auth/account')]
class UserAccountController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly string $defaultUserTimezone,
    )
    {
    }

    #[Route('/edit-profile', name: 'edit_user_profile', methods: ['GET', 'POST'])]
    public function editUserProfile(Request $request): Response
    {
        $user = $this->getUser();

        if (!$user instanceof UserEntity) {
            throw $this->createAccessDeniedException('User must be logged in to edit profile.');
        }

        if ($user->getTimezone() === null) {
            $user->setTimezone($this->defaultUserTimezone);
        }

        $form = $this->createForm(UserProfileForm::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();
            $this->addFlash('success', 'Profile updated successfully.');

            return $this->redirectToRoute('edit_user_profile');
        }

        return $this->render('auth/useradmin/user-profile.html.twig', [
            'admin_title' => 'User Profile',
            'user' => $user,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/change-password', name: 'account_change_password', methods: ['GET', 'POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function changePassword(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof UserEntity) {
            throw $this->createAccessDeniedException('You must be logged in to change your password.');
        }

        $form = $this->createForm(AccountChangePasswordForm::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string $currentPassword */
            $currentPassword = $form->get('currentPassword')->getData();

            // Validate current password before allowing a new password to be saved.
            if (!$passwordHasher->isPasswordValid($user, $currentPassword)) {
                $form->get('currentPassword')->addError(new FormError('Current password is incorrect.'));
            } else {
                /** @var string $newPlainPassword */
                $newPlainPassword = $form->get('passwordFields')->get('plainPassword')->getData();

                $user->setPassword($passwordHasher->hashPassword($user, $newPlainPassword));
                $user->setMustChangePassword(false);
                $this->entityManager->flush();

                $this->addFlash('success', 'Your password has been updated successfully.');

                return $this->redirectToRoute('edit_user_profile');
            }
        }

        return $this->render('auth/useradmin/change-password.html.twig', [
            'admin_title' => 'Change Password',
            'changePasswordForm' => $form->createView(),
        ]);
    }
}
