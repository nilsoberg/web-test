<?php

namespace App\Tests\Integration;

use App\Auth\Entity\UserEntity;
use App\Auth\Repository\UserRepository;
use App\Tests\Factory\OrganizationEntityFactory;
use App\Tests\Factory\RoleFactory;
use App\Tests\Factory\UserEntityFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class UserRepositoryTest extends KernelTestCase
{
    use Factories, ResetDatabase;

    /**
     * This test manually creates a duplicate user (same email address, different case) and verifies
     * that login attempts fail.
     */
    public function test_load_user_by_identifier_handles_duplicate_emails_gracefully(): void
    {
        self::bootKernel();

        $role = RoleFactory::createOne(['name' => 'ROLE_USER']);
        $org  = OrganizationEntityFactory::createOne(['name' => 'Main Org']);

        // Get the connection to bypass Doctrine and force the duplicate
        /** @var \Doctrine\DBAL\Connection $conn */
        $conn = self::getContainer()->get('doctrine')->getConnection();

        // Force insert two rows with same email (mixed case) + required foreign keys
        // We use the IDs from the objects we created above ($role->getId(), $org->getId())
        $sql = "
            INSERT INTO users (
                fullName, 
                email, 
                password, 
                isDeleted, 
                primary_organization_id, 
                global_role_id,
                devFeaturesEnabled,
                isPublicGhost,
                isPublicVerified
            )
            VALUES 
            ('User One', 'duplicate@example.com', 'hash1', 0, :orgId, :roleId, false, false, true),
            ('User Two', 'DUPLICATE@example.com', 'hash2', 0, :orgId, :roleId, false, false, true)
        ";

        $conn->executeStatement($sql, [
            'orgId' => $org->getId(),
            'roleId' => $role->getId(),
        ]);

        $repository = static::getContainer()->get(UserRepository::class);

        $this->expectException(UserNotFoundException::class);
        $this->expectExceptionMessage('User not found.');
        $user = $repository->loadUserByIdentifier('duplicate@example.com');
    }

    /**
     * This test verifies that a user cannot be created with a duplicate email address
     */
    public function test_create_duplicate_email(): void
    {
        self::bootKernel();

        UserEntityFactory::createOne(['email' => 'unique@example.com']);

        // Expect an exception when creating the second
        $this->expectException(\Doctrine\DBAL\Exception\UniqueConstraintViolationException::class);

        // Attempt to create the duplicate
        UserEntityFactory::createOne(['email' => 'UNIQUE@example.com']);
    }

    /**
     * This test verifies that the email address of a user cannot be changed to the email
     * address of an existing user.
     */
    public function test_edit_duplicate_email(): void
    {
        self::bootKernel();
        $em = static::getContainer()->get('doctrine')->getManager();

        $userA = UserEntityFactory::createOne(['email' => 'user-a@example.com'])->_real();
        $userB = UserEntityFactory::createOne(['email' => 'user-b@example.com'])->_real();

        // Attempt to change B's email to A's email
        $userB->setEmail('user-a@example.com');

        $this->expectException(\Doctrine\DBAL\Exception\UniqueConstraintViolationException::class);

        $em->flush();
    }

    /**
     * This test verifies that Symfony's validation catches invalid email addresses.
     * Password validation is intentionally not covered at entity level.
     */
    public function test_email_column_integrity(): void
    {
        self::bootKernel();

        $validator = static::getContainer()->get('validator');

        // Create a user with an invalid email
        $user = new UserEntity();
        $user->setEmail('test@');
        $user->setPassword('dummy-password');

        // The validation should fail for the email field
        $errors = $validator->validate($user);

        $this->assertCount(1, $errors, 'Validation should fail for invalid email.');
        $this->assertSame('email', $errors[0]->getPropertyPath());
        $this->assertEquals('This value is not a valid email address.', $errors[0]->getMessage());
    }

    /**
     * Verify that password constraints are not enforced at the entity validation layer.
     */
    public function test_empty_password_is_not_validated_at_entity_level(): void
    {
        // Boot the kernel to get the container
        self::bootKernel();

        $validator = static::getContainer()->get('validator');

        // Create a user with an empty string password
        $user = new UserEntity();
        $user->setEmail('test@example.com');
        $user->setPassword(''); // Empty string

        // Entity-level validation does not include password constraints
        $errors = $validator->validate($user);

        $this->assertCount(0, $errors, 'Empty password is currently allowed at entity validation level.');
    }
}
