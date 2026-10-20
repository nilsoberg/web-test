<?php

namespace App\Tests\Service;

use App\Auth\Entity\Role;
use App\Auth\Service\GlobalRoleService;
use App\Tests\BaseServiceTestCase;

class InitializeRolesServiceTest extends BaseServiceTestCase
{
    private GlobalRoleService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = self::getContainer()->get(GlobalRoleService::class);
    }

    public function testCreateAllRolesAddsMissingRoles(): void
    {
        // Arrange: Ensure DB is empty of roles initially
        $repo = self::getContainer()->get('doctrine')->getRepository(Role::class);
        $this->assertCount(0, $repo->findAll());

        // Act: Run the service
        $count = $this->service->initializeRoles();

        // Assert: Roles should be created
        $this->assertGreaterThan(0, $count);
        $this->assertCount($count, $repo->findAll());
    }

    public function testCreateAllRolesIsIdempotent(): void
    {
        // Arrange: Run once to fill DB
        $firstRunCount = $this->service->initializeRoles();
        $this->assertGreaterThan(0, $firstRunCount);

        // Act: Run again
        $secondRunCount = $this->service->initializeRoles();

        // Assert: No new roles should be created
        $this->assertEquals(0, $secondRunCount);
    }

    public function testCallbackIsTriggered(): void
    {
        $triggered = false;
        
        $this->service->initializeRoles(function ($roleName) use (&$triggered) {
            $triggered = true;
            $this->assertNotEmpty($roleName);
        });

        $this->assertTrue($triggered, 'Callback should be fired when roles are created.');
    }
}
