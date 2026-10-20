<?php

namespace App\Tests\Factory;

use App\Auth\Entity\OrganizationEntity;
use App\Auth\Repository\OrganizationRepository;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

final class OrganizationEntityFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return OrganizationEntity::class;
    }

    protected function defaults(): array|callable
    {
        return [
            'name' => 'EFI',
            'isDeleted' => false,
            'isProtected' => false,
        ];
    }

    protected function initialize(): static
    {
        return $this
            //->afterInstantiate(function(OrganizationEntity $organization): void {})
        ;
    }
}
