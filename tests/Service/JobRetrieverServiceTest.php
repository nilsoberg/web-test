<?php

namespace App\Tests\Service;

use App\Repository\JobRepository;
use App\Service\JobRetrieverService;
use App\Tests\BaseServiceTestCase;
use Symfony\Component\Uid\Uuid;

class JobRetrieverServiceTest extends BaseServiceTestCase
{
    private JobRetrieverService $service;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->service = self::getContainer()->get(JobRetrieverService::class);
    }

    public function testFindJobReturnsJobWhenFound(): void
    {
        $user = $this->createDefaultUser('Test User');
        $job = $this->createDefaultJob('Retrieval Test', $user);

        // Without this, $this->security->isGranted() inside the service returns false.
        $token = new \Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken(
            $user, 
            'main', 
            $user->getRoles()
        );
        $this->getContainer()->get('security.token_storage')->setToken($token);

        $jobId = $job->getId();
        $uuid = $job->getUuid();

        $result = $this->service->findJob($jobId, (string)$uuid);

        $this->assertNotNull($result);
        $this->assertSame($job, $result);
        $this->assertEquals('Retrieval Test', $result->getJobName());
    }

    public function testFindJobReturnsNullWhenNotFound(): void
    {
        $randomUuid = Uuid::v4()->toRfc4122();

        $result = $this->service->findJob(99999, $randomUuid);

        $this->assertNull($result);
    }
}
