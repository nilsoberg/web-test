<?php

namespace App\Tests\Controller;

use App\Auth\Roles;
use App\Entity\ESTGenerateFamiliesJob;
use App\Auth\Entity\OrganizationEntity;
use App\Auth\Entity\UserEntity;
use App\Tests\BaseControllerTestCase;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Browser\Test\HasBrowser;

class JobAdminControllerTest extends BaseControllerTestCase
{
    use HasBrowser;

    /**
     * Test that an Org Admin can view a job belonging to their organization.
     */
    public function testOrgAdminCanViewOwnOrgJob(): void
    {
        $orgB = $this->createOrg('Org B');
        $adminB = $this->createOrgAdmin('Admin B', $orgB);

        // Create a job in Org B
        $jobB = $this->createDefaultJob('Test Job', $adminB);

        // Log in as Admin A and try to view job belonging to Admin B/Org B
        $this->browser()
            ->actingAs($adminB)
            ->get('/admin/job/' . $jobB->getId())
            ->assertSuccessful();
    }

    /**
     * Test that an Org Admin can view a job belonging to their organization.
     */
    public function testOrgAdminCanViewOtherUserSameOrgJob(): void
    {
        $org = $this->createOrg('Org');
        $admin = $this->createOrgAdmin('Admin', $org);
        $user = $this->createDefaultUser('User', $org);

        // Create a job in Org B
        $job = $this->createDefaultJob('Test Job', $user);

        // Log in as Admin A and try to view job belonging to Admin B/Org B
        $this->browser()
            ->actingAs($admin)
            ->get('/admin/job/' . $job->getId())
            ->assertSuccessful();
    }

    /**
     * Test that an Org Admin cannot view a job belonging to a different organization.
     */
    public function testOrgAdminCannotViewOtherOrgJob(): void
    {
        // Create two organizations
        $orgA = $this->createOrg('Org A');
        $orgB = $this->createOrg('Org B');

        // Create one user in each org
        $adminA = $this->createOrgAdmin('Admin A', $orgA);
        $adminB = $this->createOrgAdmin('Admin B', $orgB);

        // Create a job in Org B
        $jobB = $this->createDefaultJob('Test Job', $adminB);

        // Log in as Admin A and try to view job belonging to Admin B/Org B
        $this->browser()
            ->actingAs($adminA)
            ->get('/admin/job/' . $jobB->getId())
            ->assertStatus(403);
    }

    /**
     * Test that the reflection logic correctly extracts subclass properties.
     */
    public function testShowReturnsSpecificJobProperties(): void
    {
        // Super Admin can see everything
        $superAdmin = $this->createSuperAdmin('Super Admin');

        // Create a specific Job type with unique properties
        $job = $this->createDefaultJob('Test Job', $superAdmin);

        $this->browser()
            ->actingAs($superAdmin)
            ->get('/admin/job/' . $job->getId())
            ->assertSuccessful()
            ->assertContains('ESTGenerateFamiliesJob')
            ->assertContains('Name')
            ->assertContains('Test Job');
    }
}
