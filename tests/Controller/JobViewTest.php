<?php

namespace App\Tests\Controller;

use App\Tests\BaseControllerTestCase;
use Zenstruck\Browser\Test\HasBrowser;

class JobViewTest extends BaseControllerTestCase
{
    use HasBrowser;

    /**
     * Test that normal user can view their own job.
     */
    public function testUserCanViewOwnJob(): void
    {
        $user = $this->createDefaultUser('User');

        // Create a job
        $job = $this->createDefaultJob('Test Job', $user);

        // Log in as normal user and try to view their own job
        $this->browser()
            ->actingAs($user)
            ->get('/efi-est/results/dataset/' . $job->getId() . '/' . $job->getUuid())
            ->assertSuccessful();
    }

    /**
     * Test that normal user can't view other user's jobs.
     */
    public function testUserCantViewOtherUserJob(): void
    {
        $orgA = $this->createOrg('Org A');
        $orgB = $this->createOrg('Org B');
        $userA = $this->createDefaultUser('User A', $orgA);
        $userB = $this->createDefaultUser('User B', $orgB);

        // Create a job
        $job = $this->createDefaultJob('Test Job', $userA);
        $this->setJobId($job, 45);

        // Log in as normal user and try to view other user's job
        $this->browser()
            ->actingAs($userB)
            ->post('/api/job-status/' . $job->getId() . '/' . $job->getUuid())
            ->assertStatus(404);
    }
}
