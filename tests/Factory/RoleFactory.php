<?php

namespace App\Tests\Factory;

use App\Auth\Entity\Role;
use App\Auth\Roles;
use App\Repository\RoleRepository;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

final class RoleFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return Role::class;
    }

    protected function defaults(): array|callable
    {
        return [
            'name' => Roles::ROLE_USER, // Default to basic user
        ];
    }

    /**
     * Helper to easily fetch/create specific roles
     */
    public static function findOrCreateRole(string $roleName): Role
    {
        return self::findOrCreate(['name' => $roleName]);
    }
}
