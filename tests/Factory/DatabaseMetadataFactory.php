<?php

namespace App\Tests\Factory;

use App\Entity\SequenceDatabaseMetadata;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

class DatabaseMetadataFactory extends PersistentProxyObjectFactory
{
    public static function class(): string { return SequenceDatabaseMetadata::class; }
    protected function defaults(): array {
        return [
            'publicName' => 'Test DB database',
            'dbName' => 'test_db',
            'isActive' => true,
            'isPublic' => false,
        ];
    }
}
