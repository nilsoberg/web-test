<?php

namespace App\Tests\Controller;

use App\Tests\Factory\UserEntityFactory;
use App\Tests\BaseControllerTestCase;
use Zenstruck\Browser\Test\HasBrowser;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class TaxonomyControllerTest extends BaseControllerTestCase
{
    use HasBrowser;

    /**
     * Test Taxonomy Accession Form (TaxonomyAccessionForm)
     * Requirements: Email, Job Name, AND (Text/File for Uniprot OR Text/File for Uniref).
     */
    public function testSubmitTaxAccessionFormSuccess(): void
    {
        $user = UserEntityFactory::new()->asMember()->create();

        $this->browser()
            ->actingAs($user)
            ->visit('/taxonomy')
            ->interceptRedirects()

            ->fillField('accessionForm[email]', $user->getEmail())
            ->fillField('accessionForm[jobName]', 'Taxonomy Accession Test')
            ->fillField('accessionForm[textUniprot]', 'B0SS77')

            ->click('form[name="accessionForm"] button[type="submit"]')
            ->assertRedirectedTo('/taxonomy')
            ->followRedirect()
            ->assertSuccessful();

        $repo = $this->getRepository();
        $this->assertCount(1, $repo->findAll());

        $job = $repo->findOneBy(['jobName' => 'Taxonomy Accession Test']);
        $this->assertNotNull($job);
    }

    /**
     * Test Taxonomy FASTA Form (TaxonomyFastaForm)
     * Requirements: Email, Job Name, AND (Text OR File FASTA).
     */
    public function testSubmitTaxFastaFormSuccess(): void
    {
        $user = UserEntityFactory::new()->asMember()->create();

        $this->browser()
            ->actingAs($user)
            ->visit('/taxonomy')
            ->interceptRedirects()

            ->fillField('fastaForm[email]', $user->getEmail())
            ->fillField('fastaForm[jobName]', 'Taxonomy FASTA Test')

            ->fillField('fastaForm[textUniprot]', '>Header\nMKTIIALSYIFCLVFADYKDDDDA')

            ->click('form[name="fastaForm"] button[type="submit"]')
            ->assertRedirectedTo('/taxonomy')
            ->followRedirect()
            ->assertSuccessful();

        $this->assertCount(1, $this->getRepository()->findAll());
    }

    /**
     * Test Taxonomy Families Form (TaxonomyFamiliesForm)
     * Requirements: Email, Job Name, Family Input.
     */
    public function testSubmitTaxFamiliesFormSuccess(): void
    {
        $user = UserEntityFactory::new()->asMember()->create();

        $this->browser()
            ->actingAs($user)
            ->visit('/taxonomy')
            ->interceptRedirects()

            ->fillField('familiesForm[email]', $user->getEmail())
            ->fillField('familiesForm[jobName]', 'Taxonomy Families Test')

            ->fillField('familiesForm[familyInput][families]', 'PF00001')

            ->click('form[name="familiesForm"] button[type="submit"]')
            ->assertRedirectedTo('/taxonomy')
            ->followRedirect()
            ->assertSuccessful();

        $this->assertCount(1, $this->getRepository()->findAll());
    }

    /**
     * Test Validation Failure: Taxonomy Accession Form
     * Scenario: User provides NO input (empty IDs).
     */
    public function testSubmitTaxAccessionFormValidationFailure(): void
    {
        $user = UserEntityFactory::new()->asMember()->create();

        $this->browser()
            ->actingAs($user)
            ->visit('/taxonomy')
            ->interceptRedirects()

            ->fillField('accessionForm[email]', $user->getEmail())
            ->fillField('accessionForm[jobName]', 'Invalid Job')

            // Submit without filling any ID fields
            ->click('form[name="accessionForm"] button[type="submit"]')

            // Assert: Stays on page (or 422 Unprocessable Entity) and shows error
            ->assertStatus(422) 
            ->assertSee('Please provide one of: pasted UniProt IDs');

        $this->assertCount(0, $this->getRepository()->findAll());
    }
}
