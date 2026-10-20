<?php

namespace App\Tests\Controller;

use App\Auth\Entity\UserEntity;
use App\Auth\Repository\UserRepository;
use App\Tests\BaseControllerTestCase;
use App\Tests\Factory\UserEntityFactory;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Zenstruck\Browser\Test\HasBrowser;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class AccountPasswordControllerTest extends BaseControllerTestCase
{
    use HasBrowser;

    public function testChangePasswordRequiresAuthentication(): void
    {
        $this->browser()
            ->interceptRedirects()
            ->visit('/auth/account/change-password')
            ->assertRedirectedTo('/login');
    }

    public function testChangePasswordSuccess(): void
    {
        $user = UserEntityFactory::createOne();
        $newPassword = 'NewSecure#2026Ab';

        $this->browser()
            ->actingAs($user)
            ->visit('/auth/account/change-password')
            ->assertSuccessful()
            ->interceptRedirects()
            ->fillField('account_change_password_form[currentPassword]', '123456')
            ->fillField('account_change_password_form[passwordFields][plainPassword][first]', $newPassword)
            ->fillField('account_change_password_form[passwordFields][plainPassword][second]', $newPassword)
            ->click('button[type="submit"]')
            ->assertRedirectedTo('/auth/account/edit-profile')
            ->followRedirect()
            ->assertSuccessful()
            ->assertSee('Your password has been updated successfully.');

        $updatedUser = $this->findUserById((int) $user->getId());
        $passwordHasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        $this->assertTrue($passwordHasher->isPasswordValid($updatedUser, $newPassword));
    }

    public function testChangePasswordPageShowsExpectedUxElements(): void
    {
        $user = UserEntityFactory::createOne();

        $this->browser()
            ->actingAs($user)
            ->visit('/auth/account/change-password')
            ->assertSuccessful()
            ->assertSee('Current Password')
            ->assertSee('Password rules:')
            ->assertSee('Password strength: Medium or higher')
            ->assertSee('Generate Password')
            ->assertSee('Copy and Save your password somewhere before proceeding.')
            ->assertSee('Repeat Password')
            ->assertSee('Passwords do not match')
            ->assertSeeElement('form[data-controller="change-password"]')
            ->assertSeeElement('[data-change-password-target="newPasswordInput"]')
            ->assertSeeElement('[data-change-password-target="repeatPasswordInput"]')
            ->assertSeeElement('[data-change-password-target="matchStatus"]')
            ->assertSeeElement('[data-action*="change-password#generatePassword"]')
            ->assertElementCount('[data-action*="change-password#toggleVisibility"]', 2);
    }

    public function testChangePasswordFailsWhenCurrentPasswordIsIncorrect(): void
    {
        $user = UserEntityFactory::createOne();
        $newPassword = 'AnotherSecure#2026Ab';

        $this->browser()
            ->actingAs($user)
            ->visit('/auth/account/change-password')
            ->assertSuccessful()
            ->fillField('account_change_password_form[currentPassword]', 'wrong-password')
            ->fillField('account_change_password_form[passwordFields][plainPassword][first]', $newPassword)
            ->fillField('account_change_password_form[passwordFields][plainPassword][second]', $newPassword)
            ->click('button[type="submit"]')
            ->assertSuccessful()
            ->assertSee('Current password is incorrect.');

        // Password must remain unchanged when the current-password check fails.
        $updatedUser = $this->findUserById((int) $user->getId());
        $passwordHasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        $this->assertTrue($passwordHasher->isPasswordValid($updatedUser, '123456'));
        $this->assertFalse($passwordHasher->isPasswordValid($updatedUser, $newPassword));
    }

    public function testUserMarkedForPasswordChangeIsRedirectedToChangePasswordPage(): void
    {
        $user = UserEntityFactory::createOne([
            'mustChangePassword' => true,
        ]);

        $this->browser()
            ->actingAs($user)
            ->interceptRedirects()
            ->visit('/')
            ->assertRedirectedTo('/auth/account/change-password');
    }

    public function testChangePasswordClearsMustChangePasswordFlag(): void
    {
        $user = UserEntityFactory::createOne([
            'mustChangePassword' => true,
        ]);
        $newPassword = 'ResetAfterLogin#2026Ab';

        $this->browser()
            ->actingAs($user)
            ->visit('/auth/account/change-password')
            ->assertSuccessful()
            ->interceptRedirects()
            ->fillField('account_change_password_form[currentPassword]', '123456')
            ->fillField('account_change_password_form[passwordFields][plainPassword][first]', $newPassword)
            ->fillField('account_change_password_form[passwordFields][plainPassword][second]', $newPassword)
            ->click('button[type="submit"]')
            ->assertRedirectedTo('/auth/account/edit-profile');

        $updatedUser = $this->findUserById((int) $user->getId());
        $this->assertFalse($updatedUser->getMustChangePassword());

        $passwordHasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        $this->assertTrue($passwordHasher->isPasswordValid($updatedUser, $newPassword));
    }

    private function findUserById(int $id): UserEntity
    {
        $repository = self::getContainer()->get(UserRepository::class);
        $user = $repository->find($id);

        $this->assertInstanceOf(UserEntity::class, $user);

        return $user;
    }
}
