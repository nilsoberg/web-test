<?php

namespace App\Tests\Repository;

use App\Entity\ESTGenerateFamiliesJob;
use App\Tests\Factory\ESTGenerateFamiliesJobFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

class JobPersistenceTest extends KernelTestCase
{
    use Factories;

    public function testSubclassPersistenceAndTraits(): void
    {
        self::bootKernel();
        
        // Create an entity with trait-provided data
        $job = ESTGenerateFamiliesJobFactory::createOne([
            'excludeFragments' => true,
            'addFamilies' => 'PF05544',
        ]);

        // Verify it persists to the single 'job' table with correct discriminator
        $id = $job->getId();
        $refresh = ESTGenerateFamiliesJobFactory::find($id);

        $this->assertTrue($refresh->isExcludeFragments());
        $this->assertEquals('PF05544', $refresh->getAddFamilies());
        $this->assertEquals('ESTGenerateFamiliesJob', $refresh->getShortClassName()); // Reflection test
    }
}
