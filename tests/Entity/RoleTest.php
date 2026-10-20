<?php

namespace App\Tests\Entity;

use App\Auth\Entity\Role;
use App\Auth\Roles;
use PHPUnit\Framework\TestCase;

class RoleTest extends TestCase
{
    public function testDisplayNameDelegatesToRolesClass(): void
    {
        // 1. Setup
        $role = new Role();
        $role->setName(Roles::ROLE_ORG_ADMIN);

        // 2. Execution
        $displayName = $role->getDisplayName();

        // 3. Assertion
        // Ensure it matches what the static class returns (Integration of logic)
        $this->assertEquals('Organization Admin', $displayName);
        // OR ensure it matches the Roles class specifically
        $this->assertEquals(Roles::getLabel(Roles::ROLE_ORG_ADMIN), $displayName);
    }
    
    public function testDefaultConstructor(): void
    {
        $role = new Role();
        // Ensure the collection is initialized so Doctrine doesn't crash on add()
        $this->assertNotNull($role->getUsersWithGlobalRole());
        $this->assertTrue($role->getUsersWithGlobalRole()->isEmpty());
    }
}
