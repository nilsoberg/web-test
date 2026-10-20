<?php

namespace App\Tests\Story;

use App\Enum\JobStatus;
use App\Tests\Factory\ESTGenerateFamiliesJobFactory;
use App\Tests\Factory\UserEntityFactory;
use Zenstruck\Foundry\Story;

final class CompletedESTDatasetCreationStory extends Story
{
    public function build(): void
    {
        // Create the user
        $user = UserEntityFactory::createOne(['email' => 'test@example.com']);

        // Create a completed job owned by that user
        ESTGenerateFamiliesJobFactory::createOne([
            'user' => $user,
            'status' => JobStatus::Finished,
            'jobName' => 'Rapid Test Analysis',
            'timeCreated' => new \DateTimeImmutable('-1 hour'),
            'timeCompleted' => new \DateTimeImmutable('now'),
			'addFamilies' => 'PF05544',
        ]);
    }
}
