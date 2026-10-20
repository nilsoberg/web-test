<?php

namespace App\Auth\Controller;

use App\Auth\Entity\UserEntity;
use App\Auth\Entity\OrganizationEntity;
use App\Auth\Entity\UserOrganizationRole;
use App\Auth\Roles;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Psr\Log\LoggerInterface;

#[Route('/orgadmin')]
#[IsGranted(Roles::ROLE_ORG_ADMIN)]
class OrgAdminController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private UserPasswordHasherInterface $passwordHasher;
    private CsrfTokenManagerInterface $csrfTokenManager;
    private LoggerInterface $logger;
    private string $defaultUserTimezone;

    public function __construct(
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        CsrfTokenManagerInterface $csrfTokenManager,
        LoggerInterface $logger,
        string $defaultUserTimezone,
    ) {
        $this->entityManager = $entityManager;
        $this->passwordHasher = $passwordHasher;
        $this->csrfTokenManager = $csrfTokenManager;
        $this->logger = $logger;
        $this->defaultUserTimezone = $defaultUserTimezone;
    }

    /**
     * Renders the Org Admin Users page.
     *
     * @return Response
     */
    #[Route('/users', name: 'orgadmin_users', methods: ['GET'])]
    public function users(): Response
    {
        /** @var UserEntity $currentUser */
        $currentUser = $this->getUser();

        // Fetch the organization where the current user is the admin
        $organization = $this->entityManager->getRepository(OrganizationEntity::class)
            ->findOneBy(['admin' => $currentUser]);

        if (!$organization) {
            throw $this->createAccessDeniedException('You are not assigned to any organization.');
        }

        // Transform organization data for JavaScript
        $organizationData = [
            'id' => $organization->getId(),
            'orgId' => $organization->getOrgId(),
            'name' => $organization->getName(),
        ];

        return $this->render('auth/orgadmin/orgusers.html.twig', [
            'organization' => $organizationData,
            'ruled_title' => 'Manage Users',
        ]);
    }

    /**
     * Returns JSON data of users within the admin's organization.
     *
     * @return JsonResponse
     */
    #[Route('/api/users', name: 'orgadmin_api_users', methods: ['GET'])]
    public function getUsers(): JsonResponse
    {
        /** @var UserEntity $currentUser */
        $currentUser = $this->getUser();

        // Fetch the organization where the current user is the admin
        $organization = $this->entityManager->getRepository(OrganizationEntity::class)
            ->findOneBy(['admin' => $currentUser]);

        if (!$organization) {
            return new JsonResponse(['error' => 'Organization not found.'], Response::HTTP_BAD_REQUEST);
        }

        // Fetch users associated with this organization
        $users = $this->entityManager->getRepository(UserEntity::class)
            ->createQueryBuilder('u')
            ->innerJoin('u.organizationRoles', 'ur')
            ->where('ur.organization = :organization')
            ->setParameter('organization', $organization)
            ->getQuery()
            ->getResult();

        $data = [];

        foreach ($users as $user) {
            // Fetch roles in roganizations
            $roles = [];
            foreach ($user->getOrganizationRoles() as $userOrgRole) {
                if ($userOrgRole->getOrganization()->getId() === $organization->getId() && !$organization->isDeleted()) {
                    $roles[] = [
                        'role' => $userOrgRole->getRole(),
                    ];
                }
            }

            $data[] = [
                'user_id' => $user->getId(),
                'first_name' => $user->getFirstName(),
                'last_name' => $user->getLastName(),
                'email' => $user->getEmail(),
                'role_organizations' => $roles, // Now only roles
                'is_deleted' => $user->isDeleted(),
                'actions' => $this->renderView('auth/orgadmin/user_actions.html.twig', ['user' => $user]),
            ];
        }

        return new JsonResponse(['data' => $data]);
    }

    /**
     * Adds a new user to the admin's organization.
     *
     * @param Request $request
     * @return JsonResponse
     */
    #[Route('/api/users/add', name: 'orgadmin_add_user', methods: ['POST'])]
    public function addUser(Request $request): JsonResponse
    {
        /** @var UserEntity $currentUser */
        $currentUser = $this->getUser();

        // Fetch the organization where the current user is the admin
        $organization = $this->entityManager->getRepository(OrganizationEntity::class)
            ->findOneBy(['admin' => $currentUser]);

        if (!$organization) {
            return new JsonResponse(['error' => 'Organization not found.'], Response::HTTP_BAD_REQUEST);
        }

        // Validate CSRF token
        $csrfToken = $request->request->get('_csrf_token');
        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken('manage_users', $csrfToken))) {
            $this->logger->warning('Invalid CSRF token during user addition.', ['admin_id' => $currentUser->getId()]);
            return new JsonResponse(['error' => 'Invalid CSRF token'], Response::HTTP_FORBIDDEN);
        }

        $firstName = trim($request->request->get('first_name', ''));
        $lastName = trim($request->request->get('last_name', ''));
        $email = trim($request->request->get('email', ''));
        $password = $request->request->get('password', '');

        $role = $request->request->get('selected_roles', '');

        // Logging the received role
        $this->logger->info('Add User Request', [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'password' => !empty($password) ? '******' : '',
            'selected_roles_raw' => $role,
        ]);

        // Validation
        // TODO: Move to a service
        if (empty($firstName)) {
            return new JsonResponse(['error' => 'First name is required.'], Response::HTTP_BAD_REQUEST);
        }

        if (empty($lastName)) {
            return new JsonResponse(['error' => 'Last name is required.'], Response::HTTP_BAD_REQUEST);
        }

        if (empty($email)) {
            return new JsonResponse(['error' => 'Email is required.'], Response::HTTP_BAD_REQUEST);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return new JsonResponse(['error' => 'Invalid email format.'], Response::HTTP_BAD_REQUEST);
        }

        if (empty($role)) {
            return new JsonResponse(['error' => 'A role must be selected.'], Response::HTTP_BAD_REQUEST);
        }

        if (!empty($password) && strlen($password) < 8) {
            return new JsonResponse(['error' => 'Password must be at least 8 characters long.'], Response::HTTP_BAD_REQUEST);
        }

        // Check if email already exists
        $existingUser = $this->entityManager->getRepository(UserEntity::class)->findOneBy(['email' => $email]);
        if ($existingUser) {
            return new JsonResponse(['error' => 'Email already exists.'], Response::HTTP_CONFLICT);
        }

        $user = new UserEntity();
        $user->setFirstName($firstName);
        $user->setLastName($lastName);
        $user->setEmail($email);
        $user->setTimezone($this->defaultUserTimezone);

        if (!empty($password)) {
            $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
            $user->setPassword($hashedPassword);
        }

        // Assign global role ROLE_USER by default
        $user->setRoles([Roles::ROLE_USER]);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->updateUserOrganizationRoles($user, $role, $organization);

        // Update the user global roles based on their organization-level role
        $existingRoles = $user->getRoles();
        if (!in_array($role, $existingRoles, true)) {
            $existingRoles[] = $role;
            $user->setRoles(array_unique($existingRoles));
            $this->entityManager->flush();
        }

        // Logging
        $this->logger->info('User created successfully.', ['user_id' => $user->getId(), 'admin_id' => $currentUser->getId()]);

        return new JsonResponse(['success' => true]);
    }

    /**
     * Edits an existing user within the admin's organization.
     *
     * @param Request $request
     * @param UserEntity $user
     * @return JsonResponse
     */
    #[Route('/api/users/edit/{id}', name: 'orgadmin_edit_user', methods: ['POST'])]
    public function editUser(Request $request, UserEntity $user): JsonResponse
    {
        /** @var UserEntity $currentUser */
        $currentUser = $this->getUser();

        // Fetch the organization where the current user is the admin
        $organization = $this->entityManager->getRepository(OrganizationEntity::class)
            ->findOneBy(['admin' => $currentUser]);

        if (!$organization) {
            return new JsonResponse(['error' => 'Organization not found.'], Response::HTTP_BAD_REQUEST);
        }

        // Ensure the user being edited belongs to the organization that the admin is controlling
        $userOrgRoles = $user->getOrganizationRoles();
        $belongsToOrg = false;
        foreach ($userOrgRoles as $userOrgRole) {
            if ($userOrgRole->getOrganization()->getId() === $organization->getId()) {
                $belongsToOrg = true;
                break;
            }
        }

        if (!$belongsToOrg) {
            return new JsonResponse(['error' => 'You can only edit users within your organization.'], Response::HTTP_FORBIDDEN);
        }

        // Validate CSRF token
        $csrfToken = $request->request->get('_csrf_token');
        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken('manage_users', $csrfToken))) {
            $this->logger->warning('Invalid CSRF token during user edit.', ['admin_id' => $currentUser->getId()]);
            return new JsonResponse(['error' => 'Invalid CSRF token'], Response::HTTP_FORBIDDEN);
        }

        $firstName = trim($request->request->get('first_name', ''));
        $lastName = trim($request->request->get('last_name', ''));
        $email = trim($request->request->get('email', ''));
        $password = $request->request->get('password', '');

        $role = $request->request->get('selected_roles', '');

        // Logging the received role
        $this->logger->info('Edit User Request', [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'password' => !empty($password) ? '********' : '',
            'selected_roles_raw' => $role,
        ]);

        // Validation
        // TODO: Move to a service
        if (empty($firstName)) {
            return new JsonResponse(['error' => 'First name is required.'], Response::HTTP_BAD_REQUEST);
        }

        if (empty($lastName)) {
            return new JsonResponse(['error' => 'Last name is required.'], Response::HTTP_BAD_REQUEST);
        }

        if (empty($email)) {
            return new JsonResponse(['error' => 'Email is required.'], Response::HTTP_BAD_REQUEST);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return new JsonResponse(['error' => 'Invalid email format.'], Response::HTTP_BAD_REQUEST);
        }

        if (empty($role)) {
            return new JsonResponse(['error' => 'A role must be selected.'], Response::HTTP_BAD_REQUEST);
        }

        if (!empty($password) && strlen($password) < 8) {
            return new JsonResponse(['error' => 'Password must be at least 8 characters long.'], Response::HTTP_BAD_REQUEST);
        }

        // Check if email is being updated to an existing email
        $existingUser = $this->entityManager->getRepository(UserEntity::class)->findOneBy(['email' => $email]);
        if ($existingUser && $existingUser->getId() !== $user->getId()) {
            return new JsonResponse(['error' => 'Email already in use by another user.'], Response::HTTP_CONFLICT);
        }

        $user->setFirstName($firstName);
        $user->setLastName($lastName);
        $user->setEmail($email);

        if (!empty($password)) {
            $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
            $user->setPassword($hashedPassword);
        }


        $this->entityManager->flush();

        $this->updateUserOrganizationRoles($user, $role, $organization); // Handle as scalar

        // Logging
        $this->logger->info('User edited successfully.', ['user_id' => $user->getId(), 'admin_id' => $currentUser->getId()]);

        return new JsonResponse(['success' => true]);
    }

    /**
     * Soft deletes a user within the admin's organization.
     *
     * @param UserEntity $user
     * @return JsonResponse
     */
    #[Route('/api/users/delete/{id}', name: 'orgadmin_delete_user', methods: ['DELETE'])]
    public function deleteUser(Request $request, UserEntity $user): JsonResponse
    {
        /** @var UserEntity $currentUser */
        $currentUser = $this->getUser();

        if (!$this->isManageUsersCsrfTokenValid($request, $currentUser, 'delete')) {
            return new JsonResponse(['error' => 'Invalid CSRF token'], Response::HTTP_FORBIDDEN);
        }

        // Fetch the organization where the current user is the admin
        $organization = $this->entityManager->getRepository(OrganizationEntity::class)
            ->findOneBy(['admin' => $currentUser]);

        if (!$organization) {
            return new JsonResponse(['error' => 'Organization not found.'], Response::HTTP_BAD_REQUEST);
        }

        // Ensure the user being deleted belongs to the organization the admin is controlling
        $userOrgRoles = $user->getOrganizationRoles();
        $belongsToOrg = false;
        foreach ($userOrgRoles as $userOrgRole) {
            if ($userOrgRole->getOrganization()->getId() === $organization->getId()) {
                $belongsToOrg = true;
                break;
            }
        }

        if (!$belongsToOrg) {
            return new JsonResponse(['error' => 'You can only delete users within your organization.'], Response::HTTP_FORBIDDEN);
        }

        if ($user->isDeleted()) {
            return new JsonResponse(['error' => 'User is already deleted.'], Response::HTTP_BAD_REQUEST);
        }

        $user->setIsDeleted(true);
        $this->entityManager->flush();

        // Logging
        $this->logger->info('User deleted successfully.', ['user_id' => $user->getId(), 'admin_id' => $currentUser->getId()]);

        return new JsonResponse(['success' => true]);
    }

    /**
     * Recovers a soft-deleted user within the admin's organization.
     *
     * @param UserEntity $user
     * @return JsonResponse
     */
    #[Route('/api/users/recover/{id}', name: 'orgadmin_recover_user', methods: ['POST'])]
    public function recoverUser(Request $request, UserEntity $user): JsonResponse
    {
        /** @var UserEntity $currentUser */
        $currentUser = $this->getUser();

        if (!$this->isManageUsersCsrfTokenValid($request, $currentUser, 'recover')) {
            return new JsonResponse(['error' => 'Invalid CSRF token'], Response::HTTP_FORBIDDEN);
        }

        // Fetch the organization where the current user is the admin
        $organization = $this->entityManager->getRepository(OrganizationEntity::class)
            ->findOneBy(['admin' => $currentUser]);

        if (!$organization) {
            return new JsonResponse(['error' => 'Organization not found.'], Response::HTTP_BAD_REQUEST);
        }

        // Ensure the user being recovered belongs to the organization under control by this admin
        $userOrgRoles = $user->getOrganizationRoles();
        $belongsToOrg = false;
        foreach ($userOrgRoles as $userOrgRole) {
            if ($userOrgRole->getOrganization()->getId() === $organization->getId()) {
                $belongsToOrg = true;
                break;
            }
        }

        if (!$belongsToOrg) {
            return new JsonResponse(['error' => 'You can only recover users within your organization.'], Response::HTTP_FORBIDDEN);
        }

        if (!$user->isDeleted()) {
            return new JsonResponse(['error' => 'User is not deleted.'], Response::HTTP_BAD_REQUEST);
        }

        $user->setIsDeleted(false);
        $this->entityManager->flush();

        // Logging
        $this->logger->info('User recovered successfully.', ['user_id' => $user->getId(), 'admin_id' => $currentUser->getId()]);

        return new JsonResponse(['success' => true]);
    }

    private function isManageUsersCsrfTokenValid(Request $request, UserEntity $currentUser, string $action): bool
    {
        $csrfToken = (string) ($request->headers->get('X-CSRF-Token')
            ?? $request->request->get('_csrf_token', ''));

        if ($this->csrfTokenManager->isTokenValid(new CsrfToken('manage_users', $csrfToken))) {
            return true;
        }

        $this->logger->warning(sprintf('Invalid CSRF token during user %s.', $action), [
            'admin_id' => $currentUser->getId(),
        ]);

        return false;
    }

    /**
     * Updates the user organization roles based on the provided role
     *
     * @param UserEntity $user The user to update
     * @param string $role The role string
     * @param OrganizationEntity $adminOrganization The admin organizatio
     */
    private function updateUserOrganizationRoles(UserEntity $user, string $role, OrganizationEntity $adminOrganization): void
    {
        // Remove existing organization roles for the organization
        foreach ($user->getOrganizationRoles() as $existingRole) {
            if ($existingRole->getOrganization()->getId() === $adminOrganization->getId()) {
                $this->entityManager->remove($existingRole);
            }
        }
        $this->entityManager->flush();

        // Assign new role
        if (!in_array($role, [Roles::ROLE_USER, Roles::ROLE_ORG_ADMIN, Roles::ROLE_DATA_ADMIN])) {
            $this->logger->warning('Attempted to assign invalid role.', ['role' => $role, 'user_id' => $user->getId()]);
            return;
        }

        $userOrgRole = new UserOrganizationRole();
        $userOrgRole->setUser($user);
        $userOrgRole->setOrganization($adminOrganization);
        $userOrgRole->setRole($role);

        $this->entityManager->persist($userOrgRole);
        $this->entityManager->flush();
    }
}
