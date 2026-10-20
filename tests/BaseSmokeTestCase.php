<?php

namespace App\Tests;

use App\Auth\Entity\UserEntity;
use App\Auth\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

abstract class BaseSmokeTestCase extends WebTestCase
{
    use Factories, ResetDatabase;

    protected const TEST_USER_EMAIL = 'test@example.com';

    /**
     * Helper to get the user from the DB without boilerplate.
     * Assumes the database has already been seeded by a Story.
     */
    protected function getTestUser(): UserEntity
    {
        $user = self::getContainer()
            ->get(UserRepository::class)
            ->findOneBy(['email' => self::TEST_USER_EMAIL]);

		if (!$user) {
            throw new \LogicException(sprintf(
                'Test User "%s" not found. Did you forget to call CompletedESTDatasetCreationStory::load()?',
                self::TEST_USER_EMAIL
            ));
        }

        return $user;
    }
}
