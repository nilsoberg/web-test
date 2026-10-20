<?php

namespace App\Tests;

use App\Repository\JobRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DependencyInjection\Container;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class BaseControllerTestCase extends WebTestCase
{
    use Factories, ResetDatabase, UserHelperTrait;

    protected Container $container;
    protected EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->container = self::getContainer();
        $this->entityManager = $this->container->get('doctrine')->getManager();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    /**
     * Helper to create a temporary dummy file for upload tests.
     * Returns the absolute path to the file.
     */
    protected function createDummyFile(string $extension = 'xgmml'): string
    {
        $filename = tempnam(sys_get_temp_dir(), 'test_upload_') . '.' . $extension;
        file_put_contents($filename, 'dummy content');
        return $filename;
    }

	/**
     * Creates a real zip file so the mime-type validator passes.
     */
    protected function createRealZipFile(): string
    {
        $filename = sys_get_temp_dir() . '/test_upload_' . uniqid() . '.zip';
        $zip = new \ZipArchive();
        
        // We must successfully open and close the zip to make it valid
        if ($zip->open($filename, \ZipArchive::CREATE) === true) {
            $zip->addFromString('network.xgmml', '<graph>valid xml</graph>');
            $zip->close();
        }
        
        return $filename;
    }

    protected function getRepository(): JobRepository
    {
        return self::getContainer()->get(JobRepository::class);
    }

    protected function setApplicationToPrivateMode(): void
    {
        $_ENV['APP_ACCESS_MODE'] = \App\Auth\AccessMode::PRIVATE;
        $_SERVER['APP_ACCESS_MODE'] = \App\Auth\AccessMode::PRIVATE;
    }

    protected function setApplicationToPublicMode(): void
    {
        $_ENV['APP_ACCESS_MODE'] = \App\Auth\AccessMode::PUBLIC;
        $_SERVER['APP_ACCESS_MODE'] = \App\Auth\AccessMode::PUBLIC;
    }
}
