<?php

namespace App\Auth\Controller;

use App\Auth\Entity\OrgDatabase;
use App\Auth\Service\RsyncKeyService;
use App\Auth\Roles;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/dbadmin')]
#[IsGranted(Roles::ROLE_DATA_ADMIN)]
class DBAdminController extends AbstractController
{
    #[Route('/', name: 'dbadmin_dashboard')]
    public function dashboard(Request $request, ManagerRegistry $doctrine): Response
    {
        $orgId = $this->getCurrentOrgId($doctrine);
        $projectRoot = $this->getParameter('kernel.project_dir');
        $webPath = "$projectRoot/var/orgs/$orgId/databases/web";
        $rsyncPath = "$projectRoot/var/orgs/$orgId/databases/rsync";

        $entityManager = $doctrine->getManager();
        $repo = $entityManager->getRepository(OrgDatabase::class);

        $hostedDbs = $repo->findBy(['orgId' => $orgId]);
        $sharedDbs = $repo->createQueryBuilder('db')
            ->where('db.shared = true')
            ->andWhere('db.sharedWith IS NOT NULL')
            ->andWhere('db.orgId != :orgId')
            ->setParameter('orgId', $orgId)
            ->getQuery()
            ->getResult();

        $visibleDbs = [];

        foreach ($sharedDbs as $db) {
            $sharedWithList = array_map('trim', explode(',', $db->getSharedWith() ?? ''));
            if (in_array($orgId, $sharedWithList, true)) {
                $visibleDbs[] = $db;
            }
        }

        $databases = array_merge($hostedDbs, $visibleDbs);

        $existingUniqueFilenames = [];
        foreach ($hostedDbs as $db) {
            $existingUniqueFilenames[$db->getUniqueFilename()] = true;
        }

        $allFiles = array_merge(glob("$webPath/*"), glob("$rsyncPath/*"));
        foreach ($allFiles as $filePath) {
            if (!is_file($filePath)) continue;
            $filename = basename($filePath);
            if (isset($existingUniqueFilenames[$filename])) continue;

            $relativePath = str_replace("$projectRoot/", '', $filePath);
            $method = str_starts_with($filePath, $rsyncPath) ? 'rsync' : 'web';

            $orgDb = new OrgDatabase();
            $orgDb->setOrgId($orgId);
            $orgDb->setMethod($method);
            $orgDb->setFilename($filename);
            $orgDb->setUniqueFilename($filename);
            $orgDb->setOriginalFilename($filename);
            $orgDb->setPath($relativePath);
            $orgDb->setCreated(new \DateTime());
            $orgDb->setUpdated(new \DateTime());
            $orgDb->setName('-');
            $orgDb->setVersion(null);
            $orgDb->setShared(false);
            $orgDb->setSharedWith(null);

            $entityManager->persist($orgDb);
        }

        $entityManager->flush();

        $orgRepo = $doctrine->getRepository(\App\Auth\Entity\OrganizationEntity::class);
        $allOrgs = $orgRepo->findAll();
        $orgIds = array_map(fn($org) => $org->getOrgId(), $allOrgs);

        return $this->render('auth/dbadmin/dbadmin.html.twig', [
            'databases' => $databases,
            'orgIds' => $orgIds,
            'orgId' => $orgId,
            'ruled_title' => 'Manage Databases',
        ]);
    }

    #[Route('/upload', name: 'dbadmin_upload')]
    public function upload(Request $request, ManagerRegistry $doctrine): Response
    {
        $orgId = $this->getCurrentOrgId($doctrine);

        if ($request->isMethod('POST') && !$this->isValidDbAdminCsrfToken('dbadmin_upload', $request)) {
            return $this->redirectToRoute('dbadmin_upload');
        }

        $projectRoot = $this->getParameter('kernel.project_dir');
        $webPath = "$projectRoot/var/orgs/$orgId/databases/web";

        if (!file_exists($webPath)) {
            mkdir($webPath, 0775, true);
        }

        if ($request->isMethod('POST') && $request->files->get('upload_file') instanceof UploadedFile) {
            $uploadedFile = $request->files->get('upload_file');
            $originalName = $uploadedFile->getClientOriginalName();

            $originalExtension = pathinfo($originalName, PATHINFO_EXTENSION);
            $allowedExtensions = ['sqlite', 'sqlite3', 'db', 'sql', 'gz'];
            $ext = strtolower($originalExtension);

            if (!in_array($ext, $allowedExtensions)) {
                return $this->redirectToRoute('dbadmin_upload');
            }

            $hash = md5($originalName . random_bytes(8) . microtime(true));
            $uniqueFilename = $hash . '.' . $ext;

            try {
                $uploadedFile->move($webPath, $uniqueFilename);
            } catch (\Exception $e) {
                return $this->redirectToRoute('dbadmin_upload');
            }

            $relativePath = "var/orgs/$orgId/databases/web/$uniqueFilename";

            $name = $request->request->get('upload_name', '-');
            $version = $request->request->get('upload_version') ?: null;
            $shared = (bool) $request->request->get('upload_shared', false);
            $sharedWith = $request->request->get('upload_shared_with') ?: null;

            $entityManager = $doctrine->getManager();
            $orgDb = new OrgDatabase();
            $orgDb->setOrgId($orgId);
            $orgDb->setMethod('web');
            $orgDb->setFilename($uniqueFilename);
            $orgDb->setUniqueFilename($uniqueFilename);
            $orgDb->setOriginalFilename($originalName);
            $orgDb->setPath($relativePath);
            $orgDb->setCreated(new \DateTime());
            $orgDb->setUpdated(new \DateTime());
            $orgDb->setName($name);
            $orgDb->setVersion($version);
            $orgDb->setShared($shared);
            $orgDb->setSharedWith($sharedWith);

            $entityManager->persist($orgDb);
            $entityManager->flush();

            return $this->redirectToRoute('dbadmin_dashboard');
        }

        $orgRepo = $doctrine->getRepository(\App\Auth\Entity\OrganizationEntity::class);
        $allOrgs = $orgRepo->findAll();
        $orgIds = array_map(fn($org) => $org->getOrgId(), $allOrgs);

        return $this->render('auth/dbadmin/upload.html.twig', [
            'ruled_title' => 'Upload Database',
            'orgIds' => $orgIds,
        ]);

    }

    #[Route('/rsync-info', name: 'dbadmin_rsync_info')]
    public function rsyncInfo(Request $request, RsyncKeyService $rsyncKeyService, ManagerRegistry $doctrine): Response
    {
        $orgId = $this->getCurrentOrgId($doctrine);
        $keypair = $rsyncKeyService->generateKeyPair($orgId);
    
        $serverAddress = $this->getParameter('DBADMIN_SERVER_ADDRESS');
        $username = $this->getParameter('DBADMIN_USERNAME');
        $stagingPath = $this->getParameter('DBADMIN_STAGING_PATH') . "/$orgId";
    
        return $this->render('auth/dbadmin/rsync-info.html.twig', [
            'keyId' => $keypair['id'],
            'username' => $username,
            'serverAddress' => $serverAddress,
            'stagingPath' => $stagingPath,
            'ruled_title' => 'Rsync Upload',
        ]);
    }
    
    #[Route('/rsync-download/{keyId}', name: 'dbadmin_rsync_download')]
    public function rsyncDownload(string $keyId, ManagerRegistry $doctrine): Response
    {
        $orgId = $this->getCurrentOrgId($doctrine);
        $privateKeyPath = $this->resolveRsyncPrivateKeyPath($orgId, $keyId);

        if ($privateKeyPath === null) {
            throw $this->createNotFoundException('Private Key not found or expired.');
        }

        $response = new StreamedResponse(function () use ($privateKeyPath) {
            readfile($privateKeyPath);
        });

        $response->headers->set('Content-Type', 'application/x-pem-file');
        $response->headers->set('Content-Disposition', 'attachment; filename="rsync_key.pem"');

        return $response;
    }

    private function resolveRsyncPrivateKeyPath(string $orgId, string $keyId): ?string
    {
        if (!$this->isValidRsyncKeyId($keyId)) {
            return null;
        }

        $baseDir = $this->getParameter('kernel.project_dir') . "/var/orgs/$orgId/private-keys";
        $resolvedBaseDir = realpath($baseDir);
        if ($resolvedBaseDir === false) {
            return null;
        }

        $resolvedPath = realpath($resolvedBaseDir . DIRECTORY_SEPARATOR . $keyId);
        if ($resolvedPath === false || !is_file($resolvedPath)) {
            return null;
        }

        $basePrefix = rtrim($resolvedBaseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (!str_starts_with($resolvedPath, $basePrefix)) {
            return null;
        }

        return $resolvedPath;
    }

    private function isValidRsyncKeyId(string $keyId): bool
    {
        return preg_match('/^key_[0-9a-f]+\.[0-9]+$/', $keyId) === 1;
    }

    private function isValidDbAdminCsrfToken(string $tokenId, Request $request): bool
    {
        return $this->isCsrfTokenValid($tokenId, (string) $request->request->get('_token'));
    }

    private function getCurrentOrgId(ManagerRegistry $doctrine): string
    {
        $user = $this->getUser();
        assert($user instanceof \App\Auth\Entity\UserEntity);

        $user = $doctrine->getManager()
            ->getRepository(\App\Auth\Entity\UserEntity::class)
            ->find($user->getId());

        foreach ($user->getOrganizationRoles() as $role) {
            $roleName = $role->getRole();
            if ($roleName instanceof \App\Auth\Entity\Role) {
                $roleName = $roleName->getName();
            }

            if ($roleName === Roles::ROLE_DATA_ADMIN) {
                return $role->getOrganization()->getOrgId();
            }
        }

        throw $this->createAccessDeniedException('You are not assigned to any organization as Data Admin.');
    }

    #[Route('/edit/{id}', name: 'dbadmin_edit_database', methods: ['POST'])]
    public function editDatabase(Request $request, OrgDatabase $orgDatabase, ManagerRegistry $doctrine): Response
    {
        if (!$this->isValidDbAdminCsrfToken('dbadmin_edit_' . $orgDatabase->getId(), $request)) {
            return $this->redirectToRoute('dbadmin_dashboard');
        }

        if (!$this->isOwnedByCurrentOrg($orgDatabase, $doctrine)) {
            return $this->redirectToRoute('dbadmin_dashboard');
        }

        $entityManager = $doctrine->getManager();

        $orgDatabase->setName($request->request->get('name', ''));
        $orgDatabase->setVersion($request->request->get('version') ?: null);
        $orgDatabase->setShared((bool) $request->request->get('shared'));
        $orgDatabase->setSharedWith($request->request->get('sharedWith') ?: null);
        $orgDatabase->setUpdated(new \DateTime());

        $entityManager->flush();

        return $this->redirectToRoute('dbadmin_dashboard');
    }

    #[Route('/delete/{id}', name: 'dbadmin_delete_database', methods: ['POST'])]
    public function deleteDatabase(Request $request, int $id, ManagerRegistry $doctrine): Response
    {
        if (!$this->isValidDbAdminCsrfToken('dbadmin_delete_' . $id, $request)) {
            return $this->redirectToRoute('dbadmin_dashboard');
        }

        $em = $doctrine->getManager();
        $repo = $em->getRepository(OrgDatabase::class);
        $db = $repo->find($id);

        if (!$db) {
            throw $this->createNotFoundException('Database entry not found.');
        }

        if (!$this->isOwnedByCurrentOrg($db, $doctrine)) {
            return $this->redirectToRoute('dbadmin_dashboard');
        }

        $projectRoot = $this->getParameter('kernel.project_dir');
        $fullPath = $projectRoot . '/' . $db->getPath();

        if (file_exists($fullPath)) {
            try {
                unlink($fullPath);
            } catch (\Throwable $e) {
                return $this->redirectToRoute('dbadmin_dashboard');
            }
        }

        $em->remove($db);
        $em->flush();

        return $this->redirectToRoute('dbadmin_dashboard');
    }

    #[Route('/unshare/{id}', name: 'dbadmin_unshare_database', methods: ['POST'])]
    public function unshareDatabase(Request $request, int $id, ManagerRegistry $doctrine): Response
    {
        if (!$this->isValidDbAdminCsrfToken('dbadmin_unshare_' . $id, $request)) {
            return $this->redirectToRoute('dbadmin_dashboard');
        }

        $orgId = $this->getCurrentOrgId($doctrine);
        $em = $doctrine->getManager();
        $repo = $em->getRepository(OrgDatabase::class);

        /** @var OrgDatabase|null $db */
        $db = $repo->find($id);
        if (!$db || $db->getOrgId() === $orgId) {
            return $this->redirectToRoute('dbadmin_dashboard');
        }

        $sharedWith = $db->getSharedWith() ?? '';
        $sharedList = array_filter(array_map('trim', explode(',', $sharedWith)));
        $newList = array_diff($sharedList, [$orgId]);

        $db->setSharedWith(implode(',', $newList));
        if (empty($newList)) {
            $db->setShared(false);
        }

        $db->setUpdated(new \DateTime());
        $em->flush();

        return $this->redirectToRoute('dbadmin_dashboard');
    }

    private function isOwnedByCurrentOrg(OrgDatabase $database, ManagerRegistry $doctrine): bool
    {
        return $database->getOrgId() === $this->getCurrentOrgId($doctrine);
    }

}
