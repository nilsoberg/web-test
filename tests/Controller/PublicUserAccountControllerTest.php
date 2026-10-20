<?php

namespace App\Tests\Auth\Controller;

use App\Auth\Controller\PublicUserAccountController;
use App\Auth\Entity\UserEntity;
use App\Auth\Service\GlobalRoleService;
use App\Entity\Job;
use App\Tests\BaseControllerTestCase;
use App\Tests\Factory\UserEntityFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Browser\Test\HasBrowser;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class PublicUserAccountControllerTest extends BaseControllerTestCase
{
    use HasBrowser;

    public function test_can_view_and_submit_new_account_form(): void
    {
        $this->setApplicationToPublicMode();

        $em = static::getContainer()->get('doctrine')->getManager();
        $roleService = new GlobalRoleService($em);
        $roleService->initializeRoles();

        $this->browser()
            ->interceptRedirects()
            ->visit('/p/auth/new-account')
            ->assertSuccessful()
            ->assertSee('Create')
            ->fillField('Email', 'newuser@example.com')
            ->fillField('Full name', 'New User')
            ->fillField('Password', 'SecurePassword123!')
            ->fillField('Repeat Password', 'SecurePassword123!')
            ->click('Create') 
            ->assertRedirectedTo('/p/auth/activation-required');
    }

    public function test_unable_to_create_duplicate_email_account(): void
    {
        $this->setApplicationToPublicMode();

        $em = static::getContainer()->get('doctrine')->getManager();
        $roleService = new GlobalRoleService($em);
        $roleService->initializeRoles();

        $user = UserEntityFactory::createOne()->_real();
        $user->setEmail('existinguser@example.com');
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->browser()
            ->interceptRedirects()
            ->visit('/p/auth/new-account')
            ->assertSuccessful()
            ->assertSee('Create')
            ->fillField('Email', 'existinguser@example.com')
            ->fillField('Full name', 'New User')
            ->fillField('Password', 'SecurePassword123!')
            ->fillField('Repeat Password', 'SecurePassword123!')
            ->click('Create')
            ->assertRedirectedTo('/p/auth/new-account')
            ->assertSee('Unable to create account: This email address is already in use');
    }

    public function test_logged_in_user_cannot_create_new_account(): void
    {
        $this->setApplicationToPublicMode();

        $user = UserEntityFactory::createOne()->_real();
        $user->setEmail('logged_in@example.com');
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        
        $this->browser()
            ->actingAs($user)
            ->visit('/p/auth/new-account')
            ->assertStatus(403);
    }

    public function test_deactivate_fails_with_invalid_csrf_token(): void
    {
        $this->setApplicationToPublicMode();

        $user = UserEntityFactory::createOne()->_real();
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $tokenData = [ 'body' => [ '_token' => 'bad_token' ] ];

        $this->browser()
            ->interceptRedirects()
            ->actingAs($user)
            ->post('/p/auth/deactivate-account', $tokenData)
            ->assertStatus(403);
    }

    public function test_public_auth_routes_redirect_to_login_in_private_mode(): void
    {
        $this->setApplicationToPrivateMode(); 

        // Unauthenticated user attempts to visit the public account creation route when site is in private mode
        $this->browser()
            ->interceptRedirects()
            ->visit('/p/auth/new-account')
            ->assertRedirectedTo('/login');
    }

    public function test_authenticated_user_gets_403_for_public_routes_in_private_mode(): void
    {
        $this->setApplicationToPrivateMode();

        $user = UserEntityFactory::createOne()->_real();
        $user->setEmail('private_user@example.com');
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        // Authenticated user attempts to visit the public account creation route
        $this->browser()
            ->interceptRedirects()
            ->actingAs($user)
            ->visit('/p/auth/new-account')
            ->assertStatus(403); 
    }

    public function test_ghost_then_activate(): void
    {
        $this->setApplicationToPublicMode();

        $em = static::getContainer()->get('doctrine')->getManager();
        $roleService = new GlobalRoleService($em);
        $roleService->initializeRoles();

        $ghostEmail = 'ghostuser@example.com';

        // STEP 1: create a job without a user account, which creates a ghost user
        $this->browser()
            ->visit('/efi-est')
            ->assertSuccessful()
            ->interceptRedirects()
            ->fillField('estFamiliesForm[email]', $ghostEmail)
            ->fillField('estFamiliesForm[jobName]', 'My Functional Test Job')
            ->fillField('estFamiliesForm[familyInput][families]', 'PF00001')
            ->click('form[name="estFamiliesForm"] button[type="submit"]') 
            ->assertRedirectedTo('/efi-est')
            ->followRedirect()
            ->assertSuccessful();

        // Verify that the job was created
        $repository = $this->getRepository();
        $this->assertCount(1, $repository->findAll());

        // Verify that the ghost user was created
        $ghostUser = $this->entityManager->getRepository(UserEntity::class)->findOneBy([ 'email' => $ghostEmail ]);
        $this->assertNotNull($ghostUser, 'The ghost user was not created in the database automatically');
        $this->assertTrue($ghostUser->isPublicGhost(), 'User should be flagged as a ghost user');
        $this->assertEquals($ghostEmail, $ghostUser->getFullName(), 'Ghost full name should be the same as the email address');
        $this->assertNotNull($ghostUser->getPassword(), 'Ghost user should have a generated password');

        // Verify that the ghost user is attached to the job
        $job = $this->entityManager->getRepository(Job::class)->findOneBy(['user' => $ghostUser]);
        $this->assertNotNull($job, 'The job was not saved or linked to the ghost user');
        $this->assertEquals('My Functional Test Job', $job->getJobName());

        // STEP 2: create a user account for the formerly ghost user
        $this->browser()
            ->interceptRedirects()
            ->visit('/p/auth/new-account')
            ->assertSuccessful()
            ->assertSee('Create')
            ->fillField('Email', $ghostEmail)
            ->fillField('Full name', 'New User')
            ->fillField('Password', 'SecurePassword123!')
            ->fillField('Repeat Password', 'SecurePassword123!')
            ->click('Create') 
            ->assertRedirectedTo('/p/auth/activation-required');

        // Verify that the user was updated
        $this->entityManager->clear();
        $fullUser = $this->entityManager->getRepository(UserEntity::class)->findOneBy([ 'email' => $ghostEmail ]);

        $query = $this->entityManager->createQuery('SELECT u FROM App\Auth\Entity\UserEntity u WHERE u.email = :email');
        $query->setParameter('email', $ghostEmail);
        $query->setHint(\Doctrine\ORM\Query::HINT_REFRESH, true); // Force DB hit
        $fullUser = $query->getSingleResult();

        $this->assertNotNull($fullUser, 'The full user was not created in the database');
        $this->assertTrue($fullUser->isPublicGhost(), 'User should still be flagged as a ghost user');
        $this->assertFalse($fullUser->isPublicVerified(), 'User should not be verified');
        $this->assertEquals('New User', $fullUser->getFullName(), 'Invalid user full name');

        // STEP 3: activate the account
        $verifyEmailHelper = static::getContainer()->get(\SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface::class);
        $signatureComponents = $verifyEmailHelper->generateSignature(
            'public_verify_email',
            (string) $fullUser->getId(),
            $fullUser->getEmail(),
            ['id' => $fullUser->getId()]
        );
        $activationUrl = $signatureComponents->getSignedUrl();

        // Visit the activation page
        $this->browser()
            ->interceptRedirects()
            ->visit($activationUrl)
            ->assertRedirectedTo('/login')
            ->followRedirect()
            ->assertSuccessful();

        // Verify that the ghost status has changed
        $this->entityManager->clear();
        $query = $this->entityManager->createQuery('SELECT u FROM App\Auth\Entity\UserEntity u WHERE u.email = :email');
        $query->setParameter('email', $ghostEmail);
        $query->setHint(\Doctrine\ORM\Query::HINT_REFRESH, true); // Force DB hit
        $fullUser = $query->getSingleResult();

        $this->assertFalse($fullUser->isPublicGhost(), 'User should not be flagged as a ghost user');
        $this->assertTrue($fullUser->isPublicVerified(), 'User should be verified');
    }
}
