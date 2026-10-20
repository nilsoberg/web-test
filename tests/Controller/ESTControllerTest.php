<?php

namespace App\Tests\Controller;

use App\Auth\Entity\UserEntity;
use App\Entity\ESTGenerateFamiliesJob;
use App\Enum\JobStatus;
use App\Enum\SequenceType;
use App\Repository\JobRepository;
use App\Tests\BaseControllerTestCase;
use App\Tests\Factory\UserEntityFactory;
use Zenstruck\Browser\Test\HasBrowser;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class ESTControllerTest extends BaseControllerTestCase
{
    use HasBrowser;

    /*
        Can view results in a specific organization
            UserEntityFactory::createOne()                          ROLE_USER
        Can submit jobs in a specific organization
            UserEntityFactory::new()->asMember()->create()          ROLE_ORG_MEMBER
        Can submit jobs and administer users in a specific organization
            UserEntityFactory::new()->asOrgAdmin()->create()        ROLE_ORG_ADMIN
        Can submit jobs, and manage database metadata in a specific organization
            UserEntityFactory::new()->asDataAdmin()->create()       ROLE_DATA_ADMIN 
        Can administer organizations, users, and jobs througout the site
            UserEntityFactory::new()->asSuperAdmin()->create()      ROLE_ORG_SUPER_ADMIN
    */

    /**
     * Verify that a default user (ROLE_USER) cannot see any forms on the EST page.
     */
    public function testDefaultUserSubmissionFailure(): void
    {
        $user = UserEntityFactory::createOne();

        $this->browser()
            ->actingAs($user)
            ->visit('/efi-est')
            ->assertSuccessful()
            ->assertNotSeeElement('form');
    }

    /**
     * Verify that if a default user (ROLE_USER) somehow bypasses the form UI and manually submits
     * a POST request that the request fails.
     */
    public function testUnauthorizedSubmissionIsIgnored(): void
    {
        $user = UserEntityFactory::createOne();

        // Manually send a POST request to the submission URL
        // We replicate the URL parameters (?f=estFamiliesForm) and POST body
        $this->browser()
            ->actingAs($user)
            ->post('/efi-est?f=estFamiliesForm', [
                'body' => [
                    'estFamiliesForm' => [
                        'email' => 'unauthorized-user@example.com',
                        'jobName' => 'Illegal Job',
                        'familyInput' => 'PF00001',
                        '_token' => '...', // Symfony ignores CSRF if form handling is skipped
                    ]
                ]
            ])

            // Assert the page loads normally (the controller doesn't crash/deny)
            ->assertSuccessful(); 

        // Verify No job was created in the database
        $repository = $this->getRepository();
        $this->assertCount(0, $repository->findAll());
    }




    /**
     * Test the Accession Form (ESTGenerateAccessionForm)
     * Requirements: Email, Job Name, AND exactly one of the 4 inputs (Text/File for UniProt/UniRef)
     */
    public function testSubmitEstAccessionFormSuccess(): void
    {
        $user = UserEntityFactory::new()->asMember()->create();

        $this->browser()
            ->actingAs($user)
            ->visit('/efi-est')
            ->interceptRedirects()

            ->fillField('estAccessionForm[email]', $user->getEmail())
            ->fillField('estAccessionForm[jobName]', 'Accession Functional Test')

            // Use the 'textUniprot' field (Option A) to satisfy the "one of..." requirement
            ->fillField('estAccessionForm[textUniprot]', 'B0SS77')

            ->click('form[name="estAccessionForm"] button[type="submit"]')

            ->assertRedirectedTo('/efi-est')
            ->followRedirect()
            ->assertSuccessful();

        $repository = $this->getRepository();
        $this->assertCount(1, $repository->findAll());

        $job = $repository->findOneBy(['jobName' => 'Accession Functional Test']);
        $this->assertNotNull($job);

        // Since we submitted via 'textUniprot', the trait should have set the type to UniProt
        $this->assertEquals(SequenceType::UniProt, $job->getUnirefVersion());
    }

    /**
     * Test Validation Failure: Accession Form
     * Scenario: User submits without providing ANY IDs (Text or File).
     */
    public function testSubmitEstAccessionFormValidationFailure(): void
    {
        $user = UserEntityFactory::new()->asMember()->create();

        $this->browser()
            ->actingAs($user)
            ->visit('/efi-est')
            ->interceptRedirects()

            // Fill common fields but skip the ID fields
            ->fillField('estAccessionForm[email]', $user->getEmail())
            ->fillField('estAccessionForm[jobName]', 'Invalid Job')

            ->click('form[name="estAccessionForm"] button[type="submit"]')

            // No redirect (stay on page) and see error message
            ->assertStatus(422)
            ->assertSee('Please provide one of: pasted UniProt IDs, UniProt ID file');

        // Verify DB is empty
        $repository = $this->getRepository();
        $this->assertCount(0, $repository->findAll());
    }




    /**
     * Test the BLAST Form (ESTGenerateBlastForm) with a sequence without a header
     * Requirements: Email, Job Name, Sequence (Valid AA), Database Selection
     */
    public function testSubmitEstBlastFormSuccess(): void
    {
        $user = UserEntityFactory::new()->asMember()->create();

        $this->browser()
            ->actingAs($user)
            ->visit('/efi-est')
            ->interceptRedirects()

            ->fillField('estBlastForm[email]', $user->getEmail())
            ->fillField('estBlastForm[jobName]', 'BLAST Functional Test')

            // Sequence: Must be valid amino acids (Regex: /^[>A-Z\.\-\s\r\n]+$/i)
            ->fillField('estBlastForm[blastSequence]', 'MKTIIALSYIFCLVFADYKDDDDA')

            // Sequence Database: Select by Value (Assuming 'uniprot' is a valid backing value for the Enum)
            ->selectFieldOption('estBlastForm[unirefVersion]', 'uniprot') 

            ->click('form[name="estBlastForm"] button[type="submit"]')

            ->assertRedirectedTo('/efi-est')
            ->followRedirect()
            ->assertSuccessful();

        $repository = $this->getRepository();
        $this->assertCount(1, $repository->findAll());

        $job = $repository->findOneBy(['jobName' => 'BLAST Functional Test']);
        $this->assertNotNull($job);
    }

    /**
     * Test the BLAST Form (ESTGenerateBlastForm) with a sequence with a header
     * Requirements: Email, Job Name, Sequence (Valid AA), Database Selection
     */
    public function testSubmitEstBlastWithHeadersFormSuccess(): void
    {
        $user = UserEntityFactory::new()->asMember()->create();

        $this->browser()
            ->actingAs($user)
            ->visit('/efi-est')
            ->interceptRedirects()

            ->fillField('estBlastForm[email]', $user->getEmail())
            ->fillField('estBlastForm[jobName]', 'BLAST Functional Test')

            // Sequence: Must be valid amino acids (Regex: /^[>A-Z\.\-\s\r\n]+$/i)
            ->fillField('estBlastForm[blastSequence]', ">Sequence HEADER\nMKTIIALSYIFCLVFADYKDDDDA\n")

            // Sequence Database: Select by Value (Assuming 'uniprot' is a valid backing value for the Enum)
            ->selectFieldOption('estBlastForm[unirefVersion]', 'uniprot') 

            ->click('form[name="estBlastForm"] button[type="submit"]')

            ->assertRedirectedTo('/efi-est')
            ->followRedirect()
            ->assertSuccessful();

        $repository = $this->getRepository();
        $this->assertCount(1, $repository->findAll());

        $job = $repository->findOneBy(['jobName' => 'BLAST Functional Test']);
        $this->assertNotNull($job);
    }

    /**
     * Test the BLAST Form (ESTGenerateBlastForm) for failure without a sequence
     * Requirements: Email, Job Name, Sequence (Valid AA), Database Selection
     */
    public function testSubmitEstBlastFormFailure(): void
    {
        $user = UserEntityFactory::new()->asMember()->create();

        $this->browser()
            ->actingAs($user)
            ->visit('/efi-est')
            ->interceptRedirects()

            ->fillField('estBlastForm[email]', $user->getEmail())
            ->fillField('estBlastForm[jobName]', 'BLAST Functional Test')

            ->click('form[name="estBlastForm"] button[type="submit"]')

            ->assertStatus(422)
            ->assertSee('Please enter a BLAST sequence');

        $repository = $this->getRepository();
        $this->assertCount(0, $repository->findAll());
    }




    /**
     * Test the Families Form (ESTGenerateFamiliesForm) with a family
     * Requirements: Email, Job Name, Family
     */
    public function testSubmitEstFamiliesFormSuccess(): void
    {
        $user = UserEntityFactory::new()->asMember()->create();

        $this->browser()
            // Zenstruck knows how to log in a Factory Proxy object
            ->actingAs($user)

            // Visit the page
            ->visit('/efi-est')
            ->assertSuccessful()
            ->interceptRedirects()

            // Fill out the 'estFamiliesForm'
            ->fillField('estFamiliesForm[email]', $user->getEmail()) // Use the factory user's email
            ->fillField('estFamiliesForm[jobName]', 'My Functional Test Job')
            ->fillField('estFamiliesForm[familyInput][families]', 'PF00001')

            ->click('form[name="estFamiliesForm"] button[type="submit"]') 

            // Verify that the submission redirects back to home
            ->assertRedirectedTo('/efi-est')
            ->followRedirect()
            ->assertSuccessful();

        // We use the JobRepository directly to verify the data was saved
        $repository = $this->getRepository();
        $this->assertCount(1, $repository->findAll());

        $job = $repository->findOneBy(['jobName' => 'My Functional Test Job']);
        $this->assertNotNull($job);
        $this->assertEquals('PF00001', $job->getAddFamilies());
    }

    /**
     * Test the Families Form (ESTGenerateFamiliesForm) for failure without a family
     * Requirements: Email, Job Name, Family
     */
    public function testSubmitEstFamiliesFormFailure(): void
    {
        $user = UserEntityFactory::new()->asMember()->create();

        $this->browser()
            ->actingAs($user)

            ->visit('/efi-est')
            ->assertSuccessful()
            ->interceptRedirects()

            ->fillField('estFamiliesForm[email]', $user->getEmail())
            ->fillField('estFamiliesForm[jobName]', 'Family Test Job')

            ->click('form[name="estFamiliesForm"] button[type="submit"]') 

            ->assertStatus(422)
            ->assertSee('One or more families must be provided');

        $repository = $this->getRepository();
        $this->assertCount(0, $repository->findAll());
    }




    /**
     * Test ESTGenerateFastaForm
     * Requirements: Email, JobName, and ONE OF the input sources (Text vs File).
     */
    public function testSubmitEstFastaFormSuccess(): void
    {
        $user = UserEntityFactory::new()->asMember()->create();

        $this->browser()
            ->actingAs($user)
            ->visit('/efi-est')
            ->interceptRedirects()

            ->fillField('estFastaForm[email]', $user->getEmail())
            ->fillField('estFastaForm[jobName]', 'Fasta Functional Test')

            ->fillField('estFastaForm[textUniprot]', '>header\nMKTIIALSYIFCLVFADYKDDDDA')

            ->click('form[name="estFastaForm"] button[type="submit"]')
            ->assertRedirectedTo('/efi-est')
            ->followRedirect()
            ->assertSuccessful();

        $repository = $this->getRepository();
        $this->assertCount(1, $repository->findAll());
    }

    /**
     * Test ESTGenerateFastaForm failure (no dasta provided)
     * Requirements: Email, JobName, and ONE OF the input sources (Text vs File).
     */
    public function testSubmitEstFastaFormNoDataFailure(): void
    {
        $user = UserEntityFactory::new()->asMember()->create();

        $this->browser()
            ->actingAs($user)
            ->visit('/efi-est')
            ->interceptRedirects()

            ->fillField('estFastaForm[email]', $user->getEmail())
            ->fillField('estFastaForm[jobName]', 'Fasta Functional Test')

            ->click('form[name="estFastaForm"] button[type="submit"]')

            ->assertStatus(422)
            ->assertSee('Please provide one of: pasted sequences with UniProt');

        $repository = $this->getRepository();
        $this->assertCount(0, $repository->findAll());
    }

    /**
     * Test ESTGenerateFastaForm failure (two forms filled out)
     * Requirements: Email, JobName, and multiple input sources (Text vs File).
     */
    public function testSubmitEstFastaFormMultipleFormFailure(): void
    {
        $user = UserEntityFactory::new()->asMember()->create();
        $dummyFile = $this->createDummyFile('txt');

        $this->browser()
            ->actingAs($user)
            ->visit('/efi-est')
            ->interceptRedirects()

            ->fillField('estFastaForm[email]', $user->getEmail())
            ->fillField('estFastaForm[jobName]', 'Fasta Functional Test')
            ->fillField('estFastaForm[textUniprot]', '>header\nMKTIIALSYIFCLVFADYKDDDDA')
            ->attachFile('estFastaForm[fileUniprot]', $dummyFile)

            ->click('form[name="estFastaForm"] button[type="submit"]')

            ->assertStatus(422)
            ->assertSee('Please provide input in only one field, not multiple');

        $repository = $this->getRepository();
        $this->assertCount(0, $repository->findAll());

        @unlink($dummyFile);
    }




    /**
     * Test ESTColorSSNForm
     * Requirements: Email, SSN File.
     */
    public function testSubmitEstColorSsnFormSuccess(): void
    {
        $user = UserEntityFactory::new()->asMember()->create();
        $zipFile = $this->createRealZipFile();

        $this->browser()
            ->actingAs($user)
            ->visit('/efi-est')
            ->interceptRedirects()

            ->fillField('estColorSsnForm[email]', $user->getEmail())

            // Upload File (Must use absolute path)
            ->attachFile('estColorSsnForm[ssnFile]', $zipFile)

            ->click('form[name="estColorSsnForm"] button[type="submit"]')
            ->assertRedirectedTo('/efi-est')
            ->followRedirect()
            ->assertSuccessful();

        // 4. Verify DB
        $repository = $this->getRepository();
        $this->assertCount(1, $repository->findAll());

        // Clean up
        @unlink($zipFile);
    }

    /**
     * Test ESTNeighborhoodConnectivityForm
     * Requirements: Email, SSN File.
     */
    public function testSubmitEstNbConnFormSuccess(): void
    {
        $user = UserEntityFactory::new()->asMember()->create();
        $zipFile = $this->createRealZipFile();

        $this->browser()
            ->actingAs($user)
            ->visit('/efi-est')
            ->interceptRedirects()

            ->fillField('estNbConnForm[email]', $user->getEmail())
            ->attachFile('estNbConnForm[ssnFile]', $zipFile)

            ->click('form[name="estNbConnForm"] button[type="submit"]')
            ->assertRedirectedTo('/efi-est')
            ->followRedirect()
            ->assertSuccessful();

        $repository = $this->getRepository();
        $this->assertCount(1, $repository->findAll());

        @unlink($zipFile);
    }

    /**
     * Test ESTClusterAnalysisForm
     * Requirements: Email, SSN File, Consensus Residues.
     */
    public function testSubmitEstClusterAnalysisFormSuccess(): void
    {
        $user = UserEntityFactory::new()->asMember()->create();
        $zipFile = $this->createRealZipFile();

        $this->browser()
            ->actingAs($user)
            ->visit('/efi-est')
            ->interceptRedirects()

            ->fillField('estCAForm[email]', $user->getEmail())
            ->fillField('estCAForm[consensusResidues]', 'C')
            ->fillField('estCAForm[computeConsensusResidues]', true)
            ->attachFile('estCAForm[ssnFile]', $zipFile)

            ->click('form[name="estCAForm"] button[type="submit"]')
            ->assertRedirectedTo('/efi-est')
            ->followRedirect()
            ->assertSuccessful();

        $repository = $this->getRepository();
        $this->assertCount(1, $repository->findAll());

        @unlink($zipFile);
    }

    /**
     * Test ESTClusterAnalysisForm
     * Scenario: user checks 'Compute Consensus Residues' but does not enter anything in 'Consensus Residues'
     * Requirements: Email, SSN File.
     */
    public function testSubmitEstClusterAnalysisFormFailure(): void
    {
        $user = UserEntityFactory::new()->asMember()->create();
        $zipFile = $this->createRealZipFile();

        $this->browser()
            ->actingAs($user)
            ->visit('/efi-est')
            ->interceptRedirects()

            ->fillField('estCAForm[email]', $user->getEmail())
            ->fillField('estCAForm[computeConsensusResidues]', true)
            ->fillField('estCAForm[consensusResidues]', '')
            ->attachFile('estCAForm[ssnFile]', $zipFile)

            ->click('form[name="estCAForm"] button[type="submit"]')

            ->assertStatus(422)
            ->assertSee('"Consensus Residues" cannot be empty when "Compute Consensus Residues" is checked');

        $repository = $this->getRepository();
        $this->assertCount(0, $repository->findAll());

        @unlink($zipFile);
    }




    /**
     * Test ESTConvergenceRatioForm
     * Requirements: Email, SSN File, Alignment Score (>= 0).
     */
    public function testSubmitEstConvRatioFormSuccess(): void
    {
        $user = UserEntityFactory::new()->asMember()->create();
        $zipFile = $this->createRealZipFile();

        $this->browser()
            ->actingAs($user)
            ->visit('/efi-est')
            ->interceptRedirects()

            ->fillField('estConvRatioForm[email]', $user->getEmail())
            ->attachFile('estConvRatioForm[ssnFile]', $zipFile)

            ->fillField('estConvRatioForm[blastEValue]', '20')

            ->click('form[name="estConvRatioForm"] button[type="submit"]')
            ->assertRedirectedTo('/efi-est')
            ->followRedirect()
            ->assertSuccessful();

        $repository = $this->getRepository();
        $this->assertCount(1, $repository->findAll());

        @unlink($zipFile);
    }

    /**
     * Validation Test: ESTConvergenceRatioForm
     * Scenario: Alignment score is negative (Constraint: GreaterThan -1).
     */
    public function testSubmitEstConvRatioFormValidationFailure(): void
    {
        $user = UserEntityFactory::new()->asMember()->create();
        $zipFile = $this->createRealZipFile();

        $this->browser()
            ->actingAs($user)
            ->visit('/efi-est')
            ->interceptRedirects()

            ->fillField('estConvRatioForm[email]', $user->getEmail())
            ->attachFile('estConvRatioForm[ssnFile]', $zipFile)

            // Invalid Input: -5
            ->fillField('estConvRatioForm[blastEValue]', '-5')

            ->click('form[name="estConvRatioForm"] button[type="submit"]')

            ->assertStatus(422)
            ->assertSee('Threshold value must be >= 0.');

        $repository = $this->getRepository();
        $this->assertCount(0, $repository->findAll());

        @unlink($zipFile);
    }

    /**
     * Validation Test: ESTConvergenceRatioForm
     * Scenario: No file attached
     */
    public function testSubmitEstConvRatioFormFileValidationFailure(): void
    {
        $user = UserEntityFactory::new()->asMember()->create();
        $zipFile = $this->createRealZipFile();

        $this->browser()
            ->actingAs($user)
            ->visit('/efi-est')
            ->interceptRedirects()

            ->fillField('estConvRatioForm[email]', $user->getEmail())
            ->fillField('estConvRatioForm[blastEValue]', '5')

            ->click('form[name="estConvRatioForm"] button[type="submit"]')

            ->assertStatus(422)
            ->assertSee('Please select a file to upload');

        $repository = $this->getRepository();
        $this->assertCount(0, $repository->findAll());

        @unlink($zipFile);
    }





    /**
     * Test EST finalization form
     */
    public function testSubmitEstFinalizationFormSuccess(): void
    {
        $user = UserEntityFactory::new()->asMember()->create();

        // Force user to persist
        $em = self::getContainer()->get('doctrine')->getManager();
        $user = $em->getRepository(UserEntity::class)->find($user->getId());

        // Create a "Parent" job in the DB manually
        $parentJob = new ESTGenerateFamiliesJob();
        $parentJob->setUser($user);
        $parentJob->setJobName('Parent Job');
        $parentJob->setUser($user);
        $parentJob->setStatus(JobStatus::Finished);
        $parentJob->setAddFamilies('PF00001');

        $em->persist($parentJob);
        $em->flush();

        $url = sprintf('/efi-est/results/dataset/%d/%s', $parentJob->getId(), $parentJob->getUuid());

        $this->browser()
            ->actingAs($user)
            ->visit($url)
            ->interceptRedirects()

            ->fillField('ssnFinalizationForm[thresholdValue]', '20')
            ->fillField('ssnFinalizationForm[thresholdMetric]', 'alignment_score')
            ->fillField('ssnFinalizationForm[ssnName]', 'Finalized SSN')

            ->click('form[name="ssnFinalizationForm"] button[type="submit"]')
            ->assertRedirectedTo('/efi-est')
            ->followRedirect()
            ->assertSuccessful();

        $finalRepo = $this->getRepository();
        $this->assertCount(2, $finalRepo->findAll()); // Two jobs because of the parent job

        $childJob = $finalRepo->findOneBy(['jobName' => 'Finalized SSN']);
        $this->assertNotNull($childJob);

        // Verify the parent-child relationship
        // (Assuming the entity has getParentJob())
        $this->assertEquals($parentJob->getId(), $childJob->getParentJob()->getId());
    }
}
