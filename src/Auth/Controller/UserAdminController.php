<?php

namespace App\Auth\Controller;

use App\Auth\Entity\UserEntity;
use App\Auth\Roles;
use App\Auth\Service\SuperAdminRoleService;
use App\Auth\Voter\UserVoter;
use App\Form\UserAdminForm;
use App\Service\StateChangeFormValidationService;
use App\Service\UserManagementService;
use App\ViewModel\Factory\AdminViewModelFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/useradmin')]
class UserAdminController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly StateChangeFormValidationService $stateValidationService,
        private readonly string $defaultUserTimezone,
    ) {
    }

    #[Route('/users', name: 'useradmin_users', methods: ['GET'])]
    #[IsGranted(UserVoter::VIEW_ADMIN)]
    public function users(AdminViewModelFactory $viewModelFactory): Response
    {
        $isSuperAdmin = $this->isGranted(Roles::ROLE_SUPER_ADMIN);
        $pageViewModel = $viewModelFactory->createUserViewModel();
        return $this->render('auth/useradmin/users.html.twig', [
            'page' => $pageViewModel,
            'admin_title' => 'Admin - Users',
            'is_super_admin' => $isSuperAdmin,
        ]);
    }

    #[Route('/add-user', name: 'useradmin_add_user', methods: ['GET', 'POST'])]
    #[IsGranted(UserVoter::CREATE)]
    public function addUser(Request $request, UserManagementService $userManager, SuperAdminRoleService $superAdminService): Response
    {
        /** @var UserEntity $currentUser */
        $currentUser = $this->getUser();
        $isSuperAdmin = $this->isGranted(Roles::ROLE_SUPER_ADMIN);
        $superAdminRoleId = $superAdminService->getSuperAdminRoleId();

        $newUser = new UserEntity();
        $newUser->setTimezone($this->defaultUserTimezone);

        $form = $this->createForm(UserAdminForm::class, $newUser, [
            'editor_super_admin' => $isSuperAdmin,
            'is_edit' => false,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $userManager->create($newUser, $isSuperAdmin);
                $this->addFlash('success', 'User has been created successfully.');
                return $this->redirectToRoute('useradmin_users');
            } catch (\Exception $e) {
                $this->addFlash('danger', 'An error occurred while creating the user.');
            }
        }

        return $this->render('auth/useradmin/user-add.html.twig', [
            'form' => $form->createView(),
            'is_edit' => false,
            'admin_title' => 'Admin - Add User',
            'editor_super_admin' => $isSuperAdmin,
            'admin_role_id' => $superAdminRoleId,
        ]);
    }

    #[Route('/edit-user/{id}', name: 'useradmin_edit_user', methods: ['GET', 'POST'])]
    #[IsGranted(UserVoter::EDIT, subject: 'user')]
    public function editUser(Request $request, UserEntity $user, UserManagementService $userManager, SuperAdminRoleService $superAdminService): Response
    {
        $isSuperAdmin = $this->isGranted(Roles::ROLE_SUPER_ADMIN);
        $superAdminRoleId = $superAdminService->getSuperAdminRoleId();

        $form = $this->createForm(UserAdminForm::class, $user, [
            'editor_super_admin' => $isSuperAdmin,
            'is_edit' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('passwordFields')->get('plainPassword')->getData();
            try {
                $userManager->edit($user, $plainPassword, $isSuperAdmin);
                $this->addFlash('success', 'User has been updated successfully.');
                return $this->redirectToRoute('useradmin_users');
            } catch (\Exception $e) {
                $this->addFlash('danger', 'An error occurred while updating the user.');
            }
        }

        return $this->render('auth/useradmin/user-add.html.twig', [
            'form' => $form->createView(),
            'user' => $user,
            'is_edit' => true,
            'admin_title' => 'Admin - Edit User',
            'editor_super_admin' => $isSuperAdmin,
            'admin_role_id' => $superAdminRoleId,
        ]);
    }

    #[Route('/delete-user/{id}', name: 'useradmin_delete_user', methods: ['POST'])]
    #[IsGranted(UserVoter::EDIT, subject: 'user')]
    public function deleteUser(Request $request, UserEntity $user): Response
    {
        $csrfTokenId = 'useradmin_delete_user_' . $user->getId();

        // First verify that the request was valid (e.g. CSRF token validation by Symfony)
        if (!$this->stateValidationService->isStateChangeValid($request, $csrfTokenId)) {
            return $this->redirectToRoute('useradmin_users');
        }

        if ($user->isDeleted()) {
            $this->addFlash('warning', 'User is already deleted.');
            return $this->redirectToRoute('useradmin_users');
        }

        $user->setIsDeleted(true);
        $this->entityManager->flush();
        $this->addFlash('success', 'User has been deleted.');

        return $this->redirectToRoute('useradmin_users');
    }

    #[Route('/restore-user/{id}', name: 'useradmin_restore_user', methods: ['POST'])]
    #[IsGranted(UserVoter::EDIT, subject: 'user')]
    public function restoreUser(Request $request, UserEntity $user): Response
    {
        $csrfTokenId = 'useradmin_restore_user_' . $user->getId();

        // First verify that the request was valid (e.g. CSRF token validation by Symfony)
        if (!$this->stateValidationService->isStateChangeValid($request, $csrfTokenId)) {
            return $this->redirectToRoute('useradmin_users');
        }

        if (!$user->isDeleted()) {
            $this->addFlash('warning', 'User is not deleted.');
            return $this->redirectToRoute('useradmin_users');
        }

        $user->setIsDeleted(false);
        $this->entityManager->flush();
        $this->addFlash('success', 'User has been restored.');

        return $this->redirectToRoute('useradmin_users');
    }
}
