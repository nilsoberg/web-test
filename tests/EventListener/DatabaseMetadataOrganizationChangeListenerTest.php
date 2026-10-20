<?php

namespace App\Tests\EventListener;

use App\Tests\BaseServiceTestCase;
use App\Tests\Factory\DatabaseMetadataFactory;
use App\Tests\Factory\OrganizationEntityFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class DatabaseMetadataOrganizationChangeListenerTest extends BaseServiceTestCase
{
    public function testLinkIsSeveredWhenDatabaseChangesOrganization(): void
    {
        // Create Org A and set a specific DB as its default
        $orgA = OrganizationEntityFactory::createOne();
        $db = DatabaseMetadataFactory::createOne(['organization' => $orgA]);
        
        $orgA->_real()->setDefaultDatabase($db->_real());
        $orgA->_save();

        // Verify the setup state is linked correctly
        $this->assertSame($db->getId(), $orgA->getDefaultDatabase()?->getId());

        // Now, edit the database metadata and switch it to Org B (or clear it to null/public)
        $orgB = OrganizationEntityFactory::createOne();
        
        $db->_real()->setOrganization($orgB->_real());
        $db->_save(); // This triggers the preUpdate event listener

        // Refresh Org A from the DB to see if the link was severed automatically
        static::getContainer()->get('doctrine')->getManager()->clear();
        
        $this->assertNull(
            $orgA->getDefaultDatabase(), 
            'The old organization default pointer should be automatically unset because the DB migrated.'
        );
    }
}
