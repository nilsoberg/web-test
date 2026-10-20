<?php

namespace App\Tests\Service;

use App\Auth\Entity\UserEntity;
use App\Enum\JobStatus;
use App\Enum\SummaryJobType;
use App\Service\UserJobsSummaryService;
use App\Tests\BaseServiceTestCase;
use App\Tests\Factory\ESTGenerateFamiliesJobFactory;
use App\Tests\Factory\UserEntityFactory;

class UserJobsSummaryServiceTest extends BaseServiceTestCase
{
    private UserJobsSummaryService $service;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->service = self::getContainer()->get(UserJobsSummaryService::class);
    }

    public function testGetJobsForDisplayBuildsTreeCorrectly(): void
    {
        $em = self::getContainer()->get('doctrine')->getManager();

        $user = UserEntityFactory::createOne();
        // Force user to persist
        $user = $em->getRepository(UserEntity::class)->find($user->getId());

        $otherUser = UserEntityFactory::createOne();
        // Force user to persist
        $otherUser = $em->getRepository(UserEntity::class)->find($otherUser->getId());

        $parent = $this->createDefaultJob('Parent Job');
        $parent->setUser($user);
        $parent->setStatus(JobStatus::Finished);

        // Create Child Job (Linked to Parent)
        $child = $this->createDefaultJob('Child Job');
        $child->setUser($user);
        $child->setParentJob($parent);
        $child->setStatus(JobStatus::Finished);

        $orphan = $this->createDefaultJob('Orphan Job');
        $orphan->setUser($user);
        $orphan->setStatus(JobStatus::New);

        $other = $this->createDefaultJob('Other User');
        $other->setUser($otherUser);
        $other->setStatus(JobStatus::New);

        // We need to simulate the logged-in user for the service
        $this->loginUser($user);

        $tree = $this->service->getJobsForDisplay(SummaryJobType::EST);

        // We expect 2 root nodes: Parent and Orphan (Other User job should be excluded)
        $this->assertCount(2, $tree, 'Should have 2 root jobs (Parent and Orphan)');

        // Find Parent (ID check)
        $parentNode = null;
        foreach ($tree as $node) {
            if ($node->job->getId() === $parent->getId())
                $parentNode = $node;
        }

        $this->assertNotNull($parentNode, 'Parent job not found in tree');
        $this->assertCount(1, $parentNode->children, 'Parent should have 1 child');
        $this->assertEquals($child->getId(), $parentNode->children[0]->job->getId(), 'Child ID mismatch');
    }
    
    // Helper to simulate login for the Security service used inside UserJobsSummaryService
    private function loginUser($user): void
    {
        $container = self::getContainer();
        $container->get('security.token_storage')->setToken(
            new \Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken($user, 'main', $user->getRoles())
        );
    }
}
