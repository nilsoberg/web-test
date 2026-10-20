<?php

namespace App\Auth\Controller;

use App\Auth\Roles;
use App\Config\Settings;
use App\Entity\DatabaseMetadata;
use App\Entity\MetagenomeMetadata;
use App\Entity\SequenceDatabaseMetadata;
use App\Enum\DatabaseMetadataType;
use App\Form\DatabaseMetadataForm;
use App\Form\MetagenomeDatabaseForm;
use App\Service\ConfigurationManager;
use App\Service\DatabaseAuthorizationService;
use App\Service\StateChangeFormValidationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Cache\CacheInterface;

#[Route('/admin/databases')]
#[IsGranted(Roles::ROLE_DATA_ADMIN)]
class DatabaseMetadataController extends AbstractController
{
    public function __construct(
        private readonly CacheInterface $cache,
        private readonly ConfigurationManager $config,
        private readonly DatabaseAuthorizationService $dbAuthService,
        private readonly EntityManagerInterface $entityManager,
        private readonly StateChangeFormValidationService $stateValidationService,
    )
    {
    }

    #[Route('/', name: 'app_admin_db_index', methods: ['GET'])]
    public function index(): Response
    {
        if ($this->isGranted(Roles::ROLE_SUPER_ADMIN)) {
            $databases = $this->entityManager->getRepository(DatabaseMetadata::class)->findAllWithDefaultFlag();
        } else {
            $user = $this->getUser();
            $org = $user->getPrimaryOrganization();
            $databases = $this->entityManager->getRepository(DatabaseMetadata::class)->findAllByOrganizationAndPublic($org);
        }

        $publicDefaultDb = $this->config->get(Settings::DEFAULT_DATABASE_VERSION);

        return $this->render('auth/dbadmin/index.html.twig', [
            'databases' => $databases,
            'public_default_database' => $publicDefaultDb,
        ]);
    }

    #[Route('/new', name: 'app_admin_db_metadata_new', methods: ['GET', 'POST'])]
    public function new_db(Request $request): Response
    {
        $database = new SequenceDatabaseMetadata();
        return $this->new_database_form($request, $database, DatabaseMetadataForm::class);
    }

    #[Route('/new-metagenome', name: 'app_admin_db_metagenome_new', methods: ['GET', 'POST'])]
    public function new_metagenome(Request $request): Response
    {
        $database = new MetagenomeMetadata();
        return $this->new_database_form($request, $database, MetagenomeDatabaseForm::class);
    }

    private function new_database_form(
        Request $request,
        DatabaseMetadata $database,
        string $formClass,
    ): Response
    {
        $user = $this->getUser();

        $form = $this->createForm($formClass, $database);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // If this is not a super admin (e.g. org or data admin), set the database org
            // to be the one for the current user
            if (!$this->isGranted(Roles::ROLE_SUPER_ADMIN)) {
                $database->setOrganization($user->getPrimaryOrganization());
            }

            $this->entityManager->persist($database);
            $this->entityManager->flush();
            $this->invalidateConfigCheck();

            $this->dbAuthService->invalidateCachedUser($user);

            $this->addFlash('success', 'Database registered successfully.');
            return $this->redirectToRoute('app_admin_db_index');
        }

        return $this->render('auth/dbadmin/metadata_form.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'app_admin_db_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, DatabaseMetadata $database): Response
    {
        $user = $this->getUser();
        $org = $user->getPrimaryOrganization();

        // Security check: ensure a DATA_ADMIN isn't modifying another org's metadata
        if (!$this->isGranted(Roles::ROLE_SUPER_ADMIN) && ($database->getOrganization() !== $org || $database->isPublic())) {
            throw $this->createAccessDeniedException('You cannot manage this database metadata.');
        }

        // The form automatically binds using the Database ID provided in the URL route parameter
        if ($database->getDatabaseType() === DatabaseMetadataType::Metagenome) {
            $form = $this->createForm(MetagenomeDatabaseForm::class, $database);
        } else {
            $form = $this->createForm(DatabaseMetadataForm::class, $database);
        }
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();
            $this->invalidateConfigCheck();

            $this->dbAuthService->invalidateCachedUser($user);

            $this->addFlash('success', 'Database metadata updated successfully.');
            return $this->redirectToRoute('app_admin_db_index');
        }

        $params = [
            'database' => $database,
            'form' => $form->createView(),
        ];

        if ($database->getDatabaseType() === DatabaseMetadataType::EST) {
            $setOrgDefaultForm = $this->stateValidationService->createStateChangeForm(
                'app_admin_db_metadata_set_default',
                ['id' => $database->getId()],
                'dbadmin_default_org_db_' . $database->getId()
            );
            $setPublicDefaultForm = $this->stateValidationService->createStateChangeForm(
                'app_admin_db_metadata_set_public_default',
                ['id' => $database->getId()],
                'dbadmin_default_public_db_' . $database->getId()
            );

            $isPublicDefault = $database->getOrganization() === null
                && $database->isPublic()
                && $this->config->get(Settings::DEFAULT_DATABASE_VERSION) === $database->getDbName();

            $params['set_org_default_form'] = $setOrgDefaultForm->createView();
            $params['set_public_default_form'] = $setPublicDefaultForm->createView();
            $params['database_is_public_default'] = $isPublicDefault;
        }

        return $this->render('auth/dbadmin/metadata_form.html.twig', $params);
    }

    #[Route('/{id}/set-org-default', name: 'app_admin_db_metadata_set_default', methods: ['POST'])]
    public function setOrgDefault(Request $request, DatabaseMetadata $database): Response
    {
        $csrfTokenId = 'dbadmin_default_org_db_' . $database->getId();

        // First verify that the request was valid (e.g. CSRF token validation by Symfony)
        if (!$this->stateValidationService->isStateChangeValid($request, $csrfTokenId)) {
            return $this->redirectToRoute('app_admin_db_index');
        }

        $user = $this->getUser();
        $org = $user->getPrimaryOrganization();

        // Security check: ensure a DATA_ADMIN isn't modifying another org's metadata
        if (!$this->isGranted(Roles::ROLE_SUPER_ADMIN) && $database->getOrganization() !== $org) {
            throw $this->createAccessDeniedException('You cannot manage this database metadata.');
        }

        // Set the new default target on the OrganizationEntity mapping layer
        if ($this->isGranted(Roles::ROLE_SUPER_ADMIN)) {
            // Super admins use the entity's target org
            $database->getOrganization()->setDefaultDatabase($database);
        } else {
            // Regular Data admins modify their own primary org
            $org->setDefaultDatabase($database);
        }

        $this->entityManager->flush();

        $this->addFlash('success', sprintf('"%s" is now your organization\'s default database.', $database->getPublicName()));
        return $this->redirectToRoute('app_admin_db_index');
    }

    #[IsGranted(Roles::ROLE_SUPER_ADMIN)]
    #[Route('/{id}/set-public-default', name: 'app_admin_db_metadata_set_public_default', methods: ['POST'])]
    public function setPublicDefault(Request $request, DatabaseMetadata $database): Response
    {
        $csrfTokenId = 'dbadmin_default_public_db_' . $database->getId();

        // First verify that the request was valid (e.g. CSRF token validation by Symfony)
        if (!$this->stateValidationService->isStateChangeValid($request, $csrfTokenId)) {
            return $this->redirectToRoute('app_admin_db_index');
        }

        if (!$database->isPublic()) {
            throw $this->createAccessDeniedException('You cannot manage this database metadata.');
        }

        $this->config->set(Settings::DEFAULT_DATABASE_VERSION, $database->getDbName());
        $this->invalidateConfigCheck();

        $this->addFlash('success', sprintf('"%s" is now the public default database.', $database->getPublicName()));
        return $this->redirectToRoute('app_admin_db_index');
    }

    /**
     * When a database is created or edited, invalidate the config cache item.  This allows
     * the configuration error message to be displayed for admin users if the database is invalid.
     */
    private function invalidateConfigCheck(): void
    {
        $this->cache->delete(Settings::CACHE_ADMIN_CONFIG_VALID_KEY);
    }
}
