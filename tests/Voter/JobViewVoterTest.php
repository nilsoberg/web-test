<?php

namespace App\Tests\Voter;

use App\Auth\AccessMode;
use App\Auth\Entity\UserEntity;
use App\Auth\Voter\JobViewVoter;
use App\Entity\ESTGenerateFamiliesJob;
use App\Entity\Job;
use App\Tests\BaseServiceTestCase;
use App\Tests\Factory\ESTGenerateFamiliesJobFactory;
use App\Tests\Factory\UserEntityFactory;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

class JobViewVoterTest extends BaseServiceTestCase
{
    public function test_public_mode_allows_viewing_any_job(): void
    {
        $voter = new JobViewVoter(AccessMode::PUBLIC);
        
        $job = new ESTGenerateFamiliesJob();
        $token = $this->createMock(TokenInterface::class);

        $result = $voter->vote($token, $job, [JobViewVoter::VIEW]);

        $this->assertEquals(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function test_private_mode_allows_owner_to_view_job(): void
    {
        $voter = new JobViewVoter(AccessMode::PRIVATE);
        
        $user = UserEntityFactory::createOne();

        $job = $this->createMock(Job::class);
        $job->method('getUser')->willReturn($user);

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $result = $voter->vote($token, $job, [JobViewVoter::VIEW]);

        $this->assertEquals(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function test_private_mode_denies_non_owner_viewing_job(): void
    {
        $voter = new JobViewVoter(AccessMode::PRIVATE);
        
        $owner = UserEntityFactory::createOne();
        $viewer = UserEntityFactory::createOne();

        $job = $this->createMock(Job::class);
        $job->method('getUser')->willReturn($owner);

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($viewer);

        $result = $voter->vote($token, $job, [JobViewVoter::VIEW]);

        $this->assertEquals(VoterInterface::ACCESS_DENIED, $result);
    }
}
