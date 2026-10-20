<?php

namespace App\Tests\Controller;

use App\Tests\Factory\UserEntityFactory;
use App\Tests\BaseControllerTestCase;
use Zenstruck\Browser\Test\HasBrowser;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class GNTControllerTest extends BaseControllerTestCase
{
    use HasBrowser;

    /**
     * Test GNT GNN Form (GNTGNNForm)
     * Requirements: Email, SSN File, Neighborhood Size (3-20), Co-occurrence (0-100).
     */
    public function testSubmitGntGnnFormSuccess(): void
    {
        $user = UserEntityFactory::new()->asMember()->create();
        $zipFile = $this->createRealZipFile();

        $this->browser()
            ->actingAs($user)
            ->visit('/efi-gnt') // Assumed route
            ->interceptRedirects()

            ->fillField('gnnForm[email]', $user->getEmail())
            ->fillField('gnnForm[neighborhoodSize]', '10') // Valid: 3-20
            ->fillField('gnnForm[cooccurrence]', '20')     // Valid: 0-100
            ->attachFile('gnnForm[ssnFile]', $zipFile)

            ->click('form[name="gnnForm"] button[type="submit"]')
            ->assertRedirectedTo('/efi-gnt')
            ->followRedirect()
            ->assertSuccessful();

        $this->assertCount(1, $this->getRepository()->findAll());
        @unlink($zipFile);
    }




    /**
     * Test GNT Blast Form (GNTDiagramBlastForm)
     * Requirements: Email, Job Name, Neighborhood Size, Blast Sequence, Database.
     */
    public function testSubmitGntBlastFormSuccess(): void
    {
        $user = UserEntityFactory::new()->asMember()->create();

        $this->browser()
            ->actingAs($user)
            ->visit('/efi-gnt')
            ->interceptRedirects()

            ->fillField('blastForm[email]', $user->getEmail())
            ->fillField('blastForm[jobName]', 'GNT Blast Test')
            ->fillField('blastForm[neighborhoodSize]', '5')

            // Blast Sequence Trait: Only letters/dashes allowed
            ->fillField('blastForm[blastSequence]', 'MKTIIALSYIFCLVFADYKDDDDA')

            // Sequence Database Trait: Enum
            ->selectFieldOption('blastForm[unirefVersion]', 'uniprot')

            ->click('form[name="blastForm"] button[type="submit"]')
            ->assertRedirectedTo('/efi-gnt')
            ->followRedirect()
            ->assertSuccessful();

        $this->assertCount(1, $this->getRepository()->findAll());
    }




    /**
     * Test GNT Sequence ID Form (GNTDiagramSequenceIdForm)
     * Requirements: Email, Job Name, Neigh. Size, Database, AND (Text OR File IDs).
     */
    public function testSubmitGntIdFormSuccess(): void
    {
        $user = UserEntityFactory::new()->asMember()->create();

        $this->browser()
            ->actingAs($user)
            ->visit('/efi-gnt')
            ->interceptRedirects()

            ->fillField('sequenceIdForm[email]', $user->getEmail())
            ->fillField('sequenceIdForm[jobName]', 'GNT ID Test')
            ->fillField('sequenceIdForm[neighborhoodSize]', '5')
            ->selectFieldOption('sequenceIdForm[unirefVersion]', 'uniprot')

            // TextOrFileValidationTrait: Must provide exactly one
            ->fillField('sequenceIdForm[textUniprot]', 'P00001, P00002')

            ->click('form[name="sequenceIdForm"] button[type="submit"]')
            ->assertRedirectedTo('/efi-gnt')
            ->followRedirect()
            ->assertSuccessful();

        $this->assertCount(1, $this->getRepository()->findAll());
    }




    /**
     * Test GNT FASTA Form (GNTDiagramFastaForm)
     * Requirements: Email, Job Name, Neigh. Size, AND (Text OR File FASTA).
     * Note: This form does NOT use SequenceDatabaseTrait.
     */
    public function testSubmitGntFastaFormSuccess(): void
    {
        $user = UserEntityFactory::new()->asMember()->create();

        $this->browser()
            ->actingAs($user)
            ->visit('/efi-gnt')
            ->interceptRedirects()

            ->fillField('fastaForm[email]', $user->getEmail())
            ->fillField('fastaForm[jobName]', 'GNT FASTA Test')
            ->fillField('fastaForm[neighborhoodSize]', '5')

            // Provide FASTA content
            ->fillField('fastaForm[textUniprot]', '>Header\nMKTIIALSYIFCLVFADYKDDDDA')

            ->click('form[name="fastaForm"] button[type="submit"]')
            ->assertRedirectedTo('/efi-gnt')
            ->followRedirect()
            ->assertSuccessful();

        $this->assertCount(1, $this->getRepository()->findAll());
    }




    /**
     * Test GNT View Diagram Form (GNTViewDiagramForm)
     * Requirements: Email, GND File (zip or sqlite).
     */
    public function testSubmitGntViewFormSuccess(): void
    {
        $user = UserEntityFactory::new()->asMember()->create();
        $zipFile = $this->createRealZipFile();

        $this->browser()
            ->actingAs($user)
            ->visit('/efi-gnt')
            ->interceptRedirects()

            ->fillField('viewGndForm[email]', $user->getEmail())
            ->attachFile('viewGndForm[gndFile]', $zipFile)

            ->click('form[name="viewGndForm"] button[type="submit"]')
            ->assertRedirectedTo('/efi-gnt')
            ->followRedirect()
            ->assertSuccessful();

        $this->assertCount(1, $this->getRepository()->findAll());
        @unlink($zipFile);
    }
}
