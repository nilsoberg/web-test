<?php

namespace App\Tests\Controller;

use App\Auth\Roles;
use App\Tests\Factory\UserEntityFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class ESTPagePermissionsTest extends WebTestCase
{
    use Factories;

    public function testReadOnlyUserCannotSeeForms(): void
    {
        $client = static::createClient();

        $user = UserEntityFactory::createOne();
        $client->loginUser($user);

        $crawler = $client->request('GET', '/efi-est');

        $this->assertResponseIsSuccessful();
        
        // The controller logic says: only create forms if granted.
        // So we assert that a common form field (like the "blastSequence" textarea) is MISSING.
        $this->assertSelectorNotExists('textarea[name*="blastSequence"]', 'Read-only users should not see form inputs');
        $this->assertSelectorNotExists('button[type="submit"]', 'Read-only users should not see submit buttons');
    }

    public function testOrgMemberCanSeeForms(): void
    {
        $client = static::createClient();

        $user = UserEntityFactory::new()->asMember()->create(); 
        $client->loginUser($user);

        $client->request('GET', '/efi-est');

        $this->assertResponseIsSuccessful();
        // Now the form SHOULD exist
        $this->assertSelectorExists('textarea', 'Org members MUST see the analysis forms');
    }
}
