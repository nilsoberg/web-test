<?php

namespace App\Tests\Factory;

use App\Auth\Entity\Role;
use App\Auth\Entity\UserEntity;
use App\Auth\Repository\UserRepository;
use App\Auth\Roles;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class UserEntityFactory extends PersistentProxyObjectFactory
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    )
    {
    }

    public static function class(): string
    {
        return UserEntity::class;
    }

    protected function defaults(): array|callable
    {
        return [
            'email' => self::faker()->unique()->email(),
            'globalRole' => RoleFactory::findOrCreate(['name' => Roles::ROLE_USER]),
			'fullName' => self::faker()->name(),
            'password' => '123456', // Default plain password
            'mustChangePassword' => false,
        ];
    }

    protected function initialize(): static
    {
        return $this
            ->afterInstantiate(function(UserEntity $user) {
                // This ensures $user->getPassword() returns the hashed version
                $user->setPassword(
                    $this->passwordHasher->hashPassword($user, $user->getPassword())
                );
            });
    }

    public function asMember(): self
    {
        return $this->with(['globalRole' => RoleFactory::findOrCreate(['name' => Roles::ROLE_ORG_MEMBER])]);
    }

    public function asOrgAdmin(): self
    {
        return $this->with(['globalRole' => RoleFactory::findOrCreate(['name' => Roles::ROLE_ORG_ADMIN])]);
    }

    public function asDataAdmin(): self
    {
        return $this->with(['globalRole' => RoleFactory::findOrCreate(['name' => Roles::ROLE_DATA_ADMIN])]);
    }
    
    public function asSuperAdmin(): self
    {
        return $this->with(['globalRole' => RoleFactory::findOrCreate(['name' => Roles::ROLE_SUPER_ADMIN])]);
    }
}
