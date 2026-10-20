<?php

namespace App\Tests\Controller;

use App\Tests\BaseControllerTestCase;
use App\Tests\Factory\DatabaseMetadataFactory;
use App\Tests\Factory\OrganizationEntityFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Browser\Test\HasBrowser;

class DatabaseMetadataControllerTest extends BaseControllerTestCase
{
    use HasBrowser;

    public function testDataAdminCannotAccessOtherOrganizationDatabase(): void
    {
        $orgA = OrganizationEntityFactory::createOne();
        $orgB = OrganizationEntityFactory::createOne();

        $dbB = DatabaseMetadataFactory::createOne(['organization' => $orgB]);

        $adminA = $this->createDataAdmin('Data Admin', $orgA);

        // Try to access Org B's edit page as Admin A -> Should get 403 Access Denied
        $this->browser()
            ->actingAs($adminA)
            ->visit('/admin/databases/' . $dbB->getId() . '/edit')
            ->assertStatus(403);
    }

    public function testDataAdminCanSeeOnlyOrganizationDatabases(): void
    {
        $orgA = OrganizationEntityFactory::createOne();
        $orgB = OrganizationEntityFactory::createOne();

        $dbA = DatabaseMetadataFactory::createOne(['organization' => $orgA, 'dbName' => 'Database A']);
        $dbB = DatabaseMetadataFactory::createOne(['organization' => $orgB, 'dbName' => 'Database B']);

        $adminA = $this->createDataAdmin('Data Admin A', $orgA);
        $adminB = $this->createDataAdmin('Data Admin B', $orgB);

        // Try to access Org B's edit page as Admin A -> Should get 403 Access Denied
        $this->browser()
            ->actingAs($adminA)
            ->visit('/admin/databases/')
            ->assertContains('Database A')
            ->assertNotContains('Database B');
    }

    public function testFormValidationPreventsPublicDbWithOrganization(): void
    {
        $superAdmin = $this->createSuperAdmin('Super Admin');
        $org = OrganizationEntityFactory::createOne();

        $this->browser()
            ->actingAs($superAdmin)
            ->visit('/admin/databases/new')
            ->use(function(\Symfony\Component\BrowserKit\AbstractBrowser $browser) use ($org) {
                $crawler = $browser->getCrawler();

                $form = $crawler->selectButton('Save')->form();

                $form->setValues([
                    'database_metadata_form[publicName]'   => 'Global Public DB',
                    'database_metadata_form[dbName]'       => 'global_public_db',
                    'database_metadata_form[isPublic]'     => '1',
                    'database_metadata_form[isActive]'     => '1',
                    'database_metadata_form[organization]' => $org->getId(),
                ]);

                $browser->submit($form);
            })
            // The assertion catches the validation error message from the DatabaseMetadataForm callback
            ->assertContains('A public database cannot belong to an organization.');
    }

    public function testDbNameRegexConstraints(): void
    {
        $superAdmin = $this->createSuperAdmin('Super Admin');
        $org = OrganizationEntityFactory::createOne();

        $this->browser()
            ->actingAs($superAdmin)
            ->visit('/admin/databases/new')
            ->use(function(\Symfony\Component\BrowserKit\AbstractBrowser $browser) use ($org) {
                $crawler = $browser->getCrawler();

                $form = $crawler->selectButton('Save')->form();

                $form->setValues([
                    'database_metadata_form[publicName]'   => 'Global Public DB',
                    'database_metadata_form[dbName]'       => 'invalid/db#name',
                    'database_metadata_form[isActive]'     => '1',
                    'database_metadata_form[organization]' => $org->getId(),
                ]);

                $browser->submit($form);
            })
            ->assertContains('The internal database name can only contain letters, numbers, and underscores.');
    }
}
