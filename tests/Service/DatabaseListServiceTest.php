<?php

namespace App\Tests\Service;

use App\Auth\Entity\OrganizationEntity;
use App\Auth\Entity\UserEntity;
use App\Auth\Roles;
use App\Entity\DatabaseMetadata;
use App\Enum\DatabaseMetadataType;
use App\Repository\DatabaseMetadataRepository;
use App\Service\DatabaseListService;
use App\Tests\UserHelperTrait;
use App\Tests\BaseServiceTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

class DatabaseListServiceTest extends BaseServiceTestCase
{
    use UserHelperTrait;

    private DatabaseMetadataRepository $dbRepoMock;
    private Security $securityMock;
    private DatabaseListService $service;

    protected function setUp(): void
    {
        $this->dbRepoMock = $this->createMock(DatabaseMetadataRepository::class);
        $this->securityMock = $this->createMock(Security::class);

        $this->service = new DatabaseListService(
            $this->dbRepoMock,
            $this->securityMock
        );
    }

    public function testGetActiveDatabasesForAnonymousUser(): void
    {
        $this->securityMock->method('getUser')->willReturn(null);

        $this->dbRepoMock
            ->expects($this->once())
            ->method('findActivePublicDatabases')
            ->with(dbType: DatabaseMetadataType::EST)
            ->willReturn([]);

        $result = $this->service->getActiveDatabases();
        $this->assertSame([], $result);
    }

    public function testGetActiveDatabasesForOrganizationUser(): void
    {
        $org = $this->createOrg();
        $user = $this->createDefaultUser('User', $org);

        $this->dbRepoMock
            ->expects($this->once())
            ->method('findActiveByOrganizationAndPublic')
            ->with($org, dbType: DatabaseMetadataType::EST)
            ->willReturn([]);

        $result = $this->service->getActiveDatabases(user: $user);
        $this->assertSame([], $result);
    }

    public function testGetActiveDatabasesForSuperAdmin(): void
    {
        $user = $this->createSuperAdmin();

        $this->securityMock->method('isGranted')
            ->with(Roles::ROLE_SUPER_ADMIN)
            ->willReturn(true);

        $this->dbRepoMock
            ->expects($this->once())
            ->method('findAllActiveOrderedByName')
            ->with(dbType: DatabaseMetadataType::EST)
            ->willReturn([]);

        $result = $this->service->getActiveDatabases(user: $user);
        $this->assertSame([], $result);
    }

    public function testGetAllowedDatabaseIdsForUserExtractsIds(): void
    {
        $user = $this->createSuperAdmin();

        $db1 = $this->createMock(DatabaseMetadata::class);
        $db1->method('getId')->willReturn(10);

        $db2 = $this->createMock(DatabaseMetadata::class);
        $db2->method('getId')->willReturn(20);

        $this->dbRepoMock
            ->method('findActivePublicDatabases')
            ->willReturn([$db1, $db2]);

        $ids = $this->service->getAllowedDatabaseIdsForUser($user);

        $this->assertSame([10, 20], $ids);
    }
}
