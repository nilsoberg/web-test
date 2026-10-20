<?php

namespace App\Tests\Service;

use App\Auth\Entity\UserEntity;
use App\Service\DatabaseAuthorizationService;
use App\Service\DatabaseListService;
use App\Tests\BaseServiceTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;

class DatabaseAuthorizationServiceTest extends BaseServiceTestCase
{
    private DatabaseListService $databaseServiceMock;
    private TagAwareAdapter $cache;
    private DatabaseAuthorizationService $service;

    protected function setUp(): void
    {
        $this->databaseServiceMock = $this->createMock(DatabaseListService::class);
        // Use real in-memory cache adapter for reliable testing
        $this->cache = new TagAwareAdapter(new ArrayAdapter());

        $this->service = new DatabaseAuthorizationService(
            $this->databaseServiceMock,
            $this->cache
        );
    }

    public function testIsGrantedReturnsTrueWhenIdIsInAllowedList(): void
    {
        $user = $this->createMock(UserEntity::class);
        $user->method('getId')->willReturn(42);

        $this->databaseServiceMock
            ->expects($this->once())
            ->method('getAllowedDatabaseIdsForUser')
            ->with($user)
            ->willReturn([1, 2, 5]);

        // DB ID 2 should be allowed
        $this->assertTrue($this->service->isGranted($user, 2));
    }

    public function testIsGrantedReturnsFalseWhenIdIsNotInAllowedList(): void
    {
        $user = $this->createMock(UserEntity::class);
        $user->method('getId')->willReturn(42);

        $this->databaseServiceMock
            ->expects($this->once())
            ->method('getAllowedDatabaseIdsForUser')
            ->with($user)
            ->willReturn([1, 2, 5]);

        // DB ID 99 should be denied
        $this->assertFalse($this->service->isGranted($user, 99));
    }

    public function testIsGrantedUsesCacheOnSubsequentCalls(): void
    {
        $user = $this->createMock(UserEntity::class);
        $user->method('getId')->willReturn(42);

        // Expect underlying service call exactly ONCE due to caching
        $this->databaseServiceMock
            ->expects($this->once())
            ->method('getAllowedDatabaseIdsForUser')
            ->with($user)
            ->willReturn([10, 20]);

        // Call #1: Hits underlying service and warms cache
        $this->assertTrue($this->service->isGranted($user, 10));

        // Call #2: Serves from cache (DatabaseListService NOT called again)
        $this->assertTrue($this->service->isGranted($user, 10));
        $this->assertFalse($this->service->isGranted($user, 99));
    }

    public function testInvalidateCachedUserForcesRecheck(): void
    {
        $user = $this->createMock(UserEntity::class);
        $user->method('getId')->willReturn(42);

        // Expect underlying service call TWICE (once before invalidation, once after)
        $this->databaseServiceMock
            ->expects($this->exactly(2))
            ->method('getAllowedDatabaseIdsForUser')
            ->with($user)
            ->willReturnOnConsecutiveCalls([1], [1, 2]);

        // First check (Caches [1])
        $this->assertFalse($this->service->isGranted($user, 2));

        // Invalidate cache
        $this->service->invalidateCachedUser($user);

        // Second check (Fetches fresh data [1, 2])
        $this->assertTrue($this->service->isGranted($user, 2));
    }
}
