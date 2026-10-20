<?php

namespace App\Tests\Controller;

use App\Tests\BaseControllerTestCase;
use App\Tests\Factory\DatabaseMetadataFactory;
use App\Tests\Factory\OrganizationEntityFactory;
use Zenstruck\Browser\Test\HasBrowser;

class JobDatabaseTest extends BaseControllerTestCase
{
    use HasBrowser;

    public function testPublicUserOnlySeesPublicActiveDatabases(): void
    {
        $this->setApplicationToPublicMode();

        // Create an organization-locked database
        $org = OrganizationEntityFactory::createOne();
        DatabaseMetadataFactory::createOne(['organization' => $org, 'publicName' => 'Private Org DB', 'dbName' => 'test1', 'isActive' => true]);

        // Create a public database
        DatabaseMetadataFactory::createOne(['organization' => null, 'publicName' => 'Global Public DB 1', 'dbName' => 'test2', 'isActive' => true, 'isPublic' => true]);
        DatabaseMetadataFactory::createOne(['organization' => null, 'publicName' => 'Global Public DB 2', 'dbName' => 'test3', 'isActive' => true, 'isPublic' => true]);

        static::getContainer()->get('doctrine')->getManager()->clear();

        // Visit the new job page as an unauthenticated (public) user
        $this->browser()
            ->visit('/efi-est')
            ->assertNotContains('Private Org DB')
            ->assertContains('Global Public DB 1');
    }

    public function testPublicUserDoesNotSeeSingleDatabase(): void
    {
        $this->setApplicationToPublicMode();

        // Create an organization-locked database
        $org = OrganizationEntityFactory::createOne();
        DatabaseMetadataFactory::createOne(['organization' => $org, 'publicName' => 'Private Org DB', 'dbName' => 'test1', 'isActive' => true]);

        // Create a public database
        DatabaseMetadataFactory::createOne(['organization' => null, 'publicName' => 'Global Public DB 1', 'dbName' => 'test2', 'isActive' => true, 'isPublic' => true]);

        static::getContainer()->get('doctrine')->getManager()->clear();

        // Visit the new job page as an unauthenticated (public) user
        $this->browser()
            ->visit('/efi-est')
            ->assertNotContains('Private Org DB')
            ->assertNotContains('Global Public DB 1');
    }

    public function testSingleDatabaseNotVisible(): void
    {
        $this->setApplicationToPublicMode();

        // Create an organization-locked database
        $org = OrganizationEntityFactory::createOne();
        DatabaseMetadataFactory::createOne(['organization' => $org, 'publicName' => 'Private Org DB', 'dbName' => 'test1', 'isActive' => true]);

        // Create a public database
        DatabaseMetadataFactory::createOne(['organization' => null, 'publicName' => 'Global Public DB 2', 'dbName' => 'test3', 'isActive' => true, 'isPublic' => true]);

        static::getContainer()->get('doctrine')->getManager()->clear();

        // Visit the new job page as an unauthenticated (public) user
        $this->browser()
            ->visit('/efi-est')
            ->assertNotContains('Private Org DB')
            ->assertNotContains('Global Public DB');
    }

    public function testOrgUserSeesOwnAndPublicDatabases(): void
    {
        $org = $this->createOrg();
        $user = $this->createDefaultUser('User', $org);

        DatabaseMetadataFactory::createOne(['organization' => $org, 'publicName' => 'Our Private DB', 'isActive' => true]);
        DatabaseMetadataFactory::createOne(['organization' => null, 'publicName' => 'Global Public DB', 'isActive' => true, 'isPublic' => true]);
        DatabaseMetadataFactory::createOne(['organization' => null, 'isActive' => false, 'publicName' => 'Inactive Public DB']);

        static::getContainer()->get('doctrine')->getManager()->clear();

        $this->browser()
            ->actingAs($user)
            ->visit('/efi-est')
            ->assertContains('Our Private DB')
            ->assertContains('Global Public DB')
            ->assertNotContains('Inactive Public DB'); // Crucial: ensure inactive ones are hidden
    }
}
