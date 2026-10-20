<?php

namespace App\Tests;

use App\Auth\Entity\OrganizationEntity;
use App\Auth\Entity\UserEntity;
use App\Entity\Job;
use App\Repository\JobRepository;
use App\Tests\Factory\ESTGenerateFamiliesJobFactory;
use App\Tests\Factory\OrganizationEntityFactory;
use App\Tests\Factory\UserEntityFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\Container;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class BaseServiceTestCase extends KernelTestCase
{
    use Factories, ResetDatabase, UserHelperTrait;

    const JOB_NAME = 'Retrieval Test';

    protected Container $container;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->container = self::getContainer();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }
}
