<?php

namespace App\Auth\Controller;

use App\Auth\Entity\OrganizationEntity;
use App\Auth\Entity\UserEntity;
use App\Auth\Roles;
use App\Form\OrganizationForm;
use App\Form\SuperAdminUserForm;
use App\Service\OrganizationManagementService;
use App\Service\StateChangeFormValidationService;
use App\ViewModel\Factory\AdminViewModelFactory;
use App\ViewModel\OrganizationAdminViewModel;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/auth/superadmin')]
#[IsGranted(Roles::ROLE_SUPER_ADMIN)]
class SuperAdminController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly OrganizationManagementService $orgManager,
        private readonly StateChangeFormValidationService $stateValidationService,
    )
	{
    }

    #[Route('/add-org', name: 'superadmin_add_org', methods: ['GET', 'POST'])]
    public function addOrganization(Request $request): Response
    {
        $organization = new OrganizationEntity();
        $form = $this->createForm(OrganizationForm::class, $organization);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->orgManager->create($organization);
                $this->addFlash('success', 'Organization has been created successfully.');
                return $this->redirectToRoute('superadmin_orgs');
            } catch (\Exception $e) {
                $this->addFlash('danger', 'An error occurred while creating the organization.');
            }
        }

        return $this->render('auth/superadmin/organization-add.html.twig', [
            'form' => $form->createView(),
            'is_edit' => false,
            'admin_title' => 'Admin - Add Organization',
        ]);
    }

    #[Route('/edit-org/{id}', name: 'superadmin_edit_org', methods: ['GET', 'POST'])]
    public function editOrganization(Request $request, OrganizationEntity $organization): Response
    {
        if ($organization->isProtected()) {
            $this->addFlash('danger', 'This system organization cannot be edited.');
            return $this->redirectToRoute('superadmin_orgs');
        }

        $form = $this->createForm(OrganizationForm::class, $organization);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->orgManager->edit($organization);
                $this->addFlash('success', 'Organization has been updated successfully.');
                return $this->redirectToRoute('superadmin_orgs');
            } catch (\Exception $e) {
                $this->addFlash('danger', 'An error occurred while editing the organization.');
            }
        }

        return $this->render('auth/superadmin/organization-add.html.twig', [
            'organization' => $organization,
            'form' => $form->createView(),
            'is_edit' => true,
            'admin_title' => 'Admin - Edit Organization',
        ]);
    }

    #[Route('/orgs', name: 'superadmin_orgs', methods: ['GET'])]
    public function organizations(AdminViewModelFactory $viewModelFactory): Response
    {
        $viewModel = $viewModelFactory->createOrganizationViewModel();
        return $this->render('auth/superadmin/organizations.html.twig', [
            'page' => $viewModel,
            'admin_title' => 'Admin - Organizations',
        ]);
    }

    #[Route('/delete-org/{id}', name: 'superadmin_delete_org', methods: ['POST'])]
    public function deleteOrganization(Request $request, OrganizationEntity $organization): Response
    {
        $csrfTokenId = 'superadmin_delete_org_' . $organization->getId();

        // First verify that the request was valid (e.g. CSRF token validation by Symfony)
        if (!$this->stateValidationService->isStateChangeValid($request, $csrfTokenId)) {
            return $this->redirectToRoute('superadmin_orgs');
        }

        // Now delete the org
        if ($organization->isProtected()) {
            $this->addFlash('danger', 'This system organization cannot be deleted.');
            return $this->redirectToRoute('superadmin_orgs');
        }

        if ($organization->getUsers()->count() > 0) {
            $this->addFlash('danger', 'Cannot delete an organization that still has active or soft-deleted users assigned to it. Please reassign users first.');
            return $this->redirectToRoute('superadmin_orgs');
        }

        if ($organization->isDeleted()) {
            $this->addFlash('warning', 'Organization is already deleted.');
            return $this->redirectToRoute('superadmin_orgs');
        }

        $organization->setIsDeleted(true);
        $this->entityManager->flush();
        $this->addFlash('success', 'Organization has been deleted.');

        return $this->redirectToRoute('superadmin_orgs');
    }

    #[Route('/restore-org/{id}', name: 'superadmin_restore_org', methods: ['POST'])]
    public function restoreOrganization(Request $request, OrganizationEntity $organization): Response
    {
        $csrfTokenId = 'superadmin_restore_org_' . $organization->getId();

        // First verify that the request was valid (e.g. CSRF token validation by Symfony)
        if (!$this->stateValidationService->isStateChangeValid($request, $csrfTokenId)) {
            return $this->redirectToRoute('superadmin_orgs');
        }

        if ($organization->isProtected()) {
            $this->addFlash('danger', 'This system organization cannot be restored.');
            return $this->redirectToRoute('superadmin_orgs');
        }

        if (!$organization->isDeleted()) {
            $this->addFlash('warning', 'Organization is not deleted.');
            return $this->redirectToRoute('superadmin_orgs');
        }

        $organization->setIsDeleted(false);
        $this->entityManager->flush();
        $this->addFlash('success', 'Organization has been restored.');

        return $this->redirectToRoute('superadmin_orgs');
    }
}
