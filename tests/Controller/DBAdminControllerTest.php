<?php

namespace App\Tests\Controller;

use App\Auth\Controller\DBAdminController;
use App\Auth\Entity\OrgDatabase;
use App\Auth\Entity\OrganizationEntity;
use App\Auth\Entity\Role;
use App\Auth\Entity\UserEntity;
use App\Auth\Entity\UserOrganizationRole;
use App\Auth\Roles;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DBAdminControllerTest extends TestCase
{
    public function testEditDatabaseRejectsSharedRecordOwnedByAnotherOrg(): void
    {
        $database = (new OrgDatabase())
            ->setOrgId('ORG_B')
            ->setName('Original Name')
            ->setVersion('1.0')
            ->setShared(false)
            ->setSharedWith(null);

        $doctrine = $this->createMock(ManagerRegistry::class);
        $doctrine->expects($this->never())->method('getManager');
        $controller = $this->createControllerForCurrentOrg('ORG_A');
        $controller->expects($this->once())
            ->method('isCsrfTokenValid')
            ->with('dbadmin_edit_', 'bad-token')
            ->willReturn(false);
        $controller->expects($this->once())
            ->method('redirectToRoute')
            ->with('dbadmin_dashboard')
            ->willReturn(new RedirectResponse('/dbadmin'));

        $request = new Request([], [
            '_token' => 'bad-token',
            'name' => 'Changed Name',
            'version' => '2.0',
            'shared' => '1',
            'sharedWith' => 'ORG_C',
        ]);

        $response = $controller->editDatabase($request, $database, $doctrine);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('Original Name', $database->getName());
        $this->assertSame('1.0', $database->getVersion());
        $this->assertFalse($database->isShared());
        $this->assertNull($database->getSharedWith());
    }

    public function testEditDatabaseAllowsOwnedRecord(): void
    {
        $database = (new OrgDatabase())
            ->setOrgId('ORG_A')
            ->setName('Original Name')
            ->setVersion('1.0')
            ->setShared(false)
            ->setSharedWith(null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('flush');

        $doctrine = $this->createDoctrineForCurrentOrg('ORG_A', $entityManager);
        $controller = $this->createControllerForCurrentOrg('ORG_A');
        $controller->expects($this->once())
            ->method('isCsrfTokenValid')
            ->with('dbadmin_edit_', 'good-token')
            ->willReturn(true);
        $controller->expects($this->once())
            ->method('redirectToRoute')
            ->with('dbadmin_dashboard')
            ->willReturn(new RedirectResponse('/dbadmin'));

        $request = new Request([], [
            '_token' => 'good-token',
            'name' => 'Changed Name',
            'version' => '2.0',
            'shared' => '1',
            'sharedWith' => 'ORG_C',
        ]);

        $response = $controller->editDatabase($request, $database, $doctrine);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('Changed Name', $database->getName());
        $this->assertSame('2.0', $database->getVersion());
        $this->assertTrue($database->isShared());
        $this->assertSame('ORG_C', $database->getSharedWith());
    }

    public function testDeleteDatabaseRejectsSharedRecordOwnedByAnotherOrg(): void
    {
        $database = (new OrgDatabase())
            ->setOrgId('ORG_B')
            ->setPath('var/nonexistent.db');

        $doctrine = $this->createMock(ManagerRegistry::class);
        $doctrine->expects($this->never())->method('getManager');
        $controller = $this->createControllerForCurrentOrg('ORG_A');
        $controller->expects($this->once())
            ->method('isCsrfTokenValid')
            ->with('dbadmin_delete_99', 'bad-token')
            ->willReturn(false);
        $controller->expects($this->once())
            ->method('redirectToRoute')
            ->with('dbadmin_dashboard')
            ->willReturn(new RedirectResponse('/dbadmin'));
        $controller->expects($this->never())->method('getParameter');

        $request = new Request([], ['_token' => 'bad-token']);

        $response = $controller->deleteDatabase($request, 99, $doctrine);

        $this->assertInstanceOf(RedirectResponse::class, $response);
    }

    public function testDeleteDatabaseAllowsOwnedRecord(): void
    {
        $database = (new OrgDatabase())
            ->setOrgId('ORG_A')
            ->setPath('var/nonexistent.db');

        $databaseRepository = $this->createRepositoryMock();
        $databaseRepository->expects($this->once())
            ->method('find')
            ->with(101)
            ->willReturn($database);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())
            ->method('remove')
            ->with($database);
        $entityManager->expects($this->once())->method('flush');

        $doctrine = $this->createDoctrineForCurrentOrg('ORG_A', $entityManager, $databaseRepository);
        $controller = $this->createControllerForCurrentOrg('ORG_A');
        $controller->expects($this->once())
            ->method('isCsrfTokenValid')
            ->with('dbadmin_delete_101', 'good-token')
            ->willReturn(true);
        $controller->expects($this->once())
            ->method('getParameter')
            ->with('kernel.project_dir')
            ->willReturn('/tmp/project');
        $controller->expects($this->once())
            ->method('redirectToRoute')
            ->with('dbadmin_dashboard')
            ->willReturn(new RedirectResponse('/dbadmin'));

        $request = new Request([], ['_token' => 'good-token']);

        $response = $controller->deleteDatabase($request, 101, $doctrine);

        $this->assertInstanceOf(RedirectResponse::class, $response);
    }

    public function testRsyncDownloadRejectsInvalidKeyId(): void
    {
        $doctrine = $this->createDoctrineForCurrentOrg('ORG_A', $this->createMock(EntityManagerInterface::class));
        $controller = $this->createControllerForCurrentOrg('ORG_A');
        $controller->expects($this->never())->method('getParameter');

        $this->expectException(NotFoundHttpException::class);

        $controller->rsyncDownload('../secret', $doctrine);
    }

    public function testRsyncDownloadRejectsSymlinkOutsidePrivateKeyDirectory(): void
    {
        $projectDir = $this->createTempProjectDir();
        $privateKeysDir = $projectDir . '/var/orgs/ORG_A/private-keys';
        $outsideFile = $projectDir . '/var/orgs/ORG_A/outside-key';
        mkdir($privateKeysDir, 0777, true);
        file_put_contents($outsideFile, 'outside');
        symlink($outsideFile, $privateKeysDir . '/key_abcdef.123456');

        $doctrine = $this->createDoctrineForCurrentOrg('ORG_A', $this->createMock(EntityManagerInterface::class));
        $controller = $this->createControllerForCurrentOrg('ORG_A');
        $controller->method('getParameter')->with('kernel.project_dir')->willReturn($projectDir);

        try {
            $this->expectException(NotFoundHttpException::class);
            $controller->rsyncDownload('key_abcdef.123456', $doctrine);
        } finally {
            $this->removeDirectory($projectDir);
        }
    }

    public function testRsyncDownloadAllowsOwnedPrivateKeyFile(): void
    {
        $projectDir = $this->createTempProjectDir();
        $privateKeysDir = $projectDir . '/var/orgs/ORG_A/private-keys';
        mkdir($privateKeysDir, 0777, true);
        file_put_contents($privateKeysDir . '/key_abcdef.123456', 'private-key');

        $doctrine = $this->createDoctrineForCurrentOrg('ORG_A', $this->createMock(EntityManagerInterface::class));
        $controller = $this->createControllerForCurrentOrg('ORG_A');
        $controller->method('getParameter')->with('kernel.project_dir')->willReturn($projectDir);

        try {
            $response = $controller->rsyncDownload('key_abcdef.123456', $doctrine);

            $this->assertInstanceOf(StreamedResponse::class, $response);
            $this->assertSame('application/x-pem-file', $response->headers->get('Content-Type'));
            $this->assertSame('attachment; filename="rsync_key.pem"', $response->headers->get('Content-Disposition'));
        } finally {
            $this->removeDirectory($projectDir);
        }
    }

    private function createControllerForCurrentOrg(string $currentOrgId): DBAdminController
    {
        $user = $this->createCurrentUserMock($currentOrgId);

        $controller = $this->getMockBuilder(DBAdminController::class)
            ->onlyMethods(['getUser', 'redirectToRoute', 'getParameter', 'isCsrfTokenValid'])
            ->getMock();

        $controller->method('getUser')->willReturn($user);

        return $controller;
    }

    private function createDoctrineForCurrentOrg(
        string $currentOrgId,
        EntityManagerInterface $entityManager,
        ?EntityRepository $databaseRepository = null
    ): ManagerRegistry {
        $user = $this->createCurrentUserMock($currentOrgId);

        $userRepository = $this->createRepositoryMock();
        $userRepository->expects($this->once())
            ->method('find')
            ->with(123)
            ->willReturn($user);

        $entityManager->method('getRepository')
            ->willReturnCallback(function (string $class) use ($userRepository, $databaseRepository) {
                if ($class === UserEntity::class) {
                    return $userRepository;
                }

                if ($class === OrgDatabase::class) {
                    return $databaseRepository;
                }

                return $this->createRepositoryMock();
            });

        $doctrine = $this->createMock(ManagerRegistry::class);
        $doctrine->method('getManager')->willReturn($entityManager);

        return $doctrine;
    }

    private function createCurrentUserMock(string $currentOrgId): UserEntity
    {
        $organization = $this->getMockBuilder(OrganizationEntity::class)
            ->disableOriginalConstructor()
            ->addMethods(['getOrgId'])
            ->getMock();
        $organization->method('getOrgId')->willReturn($currentOrgId);

        $role = new Role();
        $role->setName(Roles::ROLE_DATA_ADMIN);

        $orgRole = new UserOrganizationRole();
        $orgRole->setOrganization($organization);
        $orgRole->setRole($role);

        $user = $this->getMockBuilder(UserEntity::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId'])
            ->addMethods(['getOrganizationRoles'])
            ->getMock();

        $orgRole->setUser($user);

        $user->method('getId')->willReturn(123);
        $user->method('getOrganizationRoles')->willReturn([$orgRole]);

        return $user;
    }

    private function createRepositoryMock(): EntityRepository
    {
        return $this->getMockBuilder(EntityRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['find'])
            ->getMock();
    }

    private function createTempProjectDir(): string
    {
        $tempDir = sys_get_temp_dir() . '/dbadmin-controller-test-' . bin2hex(random_bytes(8));
        mkdir($tempDir, 0777, true);

        return $tempDir;
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = scandir($path);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $itemPath = $path . '/' . $item;
            if (is_dir($itemPath) && !is_link($itemPath)) {
                $this->removeDirectory($itemPath);
                continue;
            }

            unlink($itemPath);
        }

        rmdir($path);
    }
}
