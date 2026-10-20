<?php

namespace App\Auth;

class Roles
{
    public const ROLE_DATA_ADMIN = 'ROLE_DATA_ADMIN';
    public const ROLE_ORG_ADMIN = 'ROLE_ORG_ADMIN';
    public const ROLE_ORG_MEMBER = 'ROLE_ORG_MEMBER';
    public const ROLE_SUPER_ADMIN = 'ROLE_SUPER_ADMIN';
    public const ROLE_USER = 'ROLE_USER';
    public const ROLE_USER_DISPLAY_NAME = 'Read-only User';

	public static function getOrgRoles(): array
	{
        return [ self::ROLE_SUPER_ADMIN, self::ROLE_ORG_ADMIN, self::ROLE_DATA_ADMIN, self::ROLE_ORG_MEMBER, self::ROLE_USER ];
    }

    public static function getLabel(string $role): string
    {
        return match($role) {
            self::ROLE_SUPER_ADMIN => 'Superadmin',
            self::ROLE_ORG_ADMIN => 'Organization Admin',
            self::ROLE_DATA_ADMIN => 'Data Admin',
            self::ROLE_ORG_MEMBER => 'Organization Member',
            self::ROLE_USER => self::ROLE_USER_DISPLAY_NAME,
        };
    }
}
