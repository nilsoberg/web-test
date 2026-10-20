<?php

namespace App\Tests;

use App\Auth\Entity\OrganizationEntity;
use App\Auth\Entity\UserEntity;
use App\Entity\Job;
use App\Tests\Factory\ESTGenerateFamiliesJobFactory;
use App\Tests\Factory\OrganizationEntityFactory;
use App\Tests\Factory\UserEntityFactory;

trait UserHelperTrait
{
    protected function createDefaultUser(string $fullName = 'Test User', ?OrganizationEntity $org = null): UserEntity
    {
        if (!$org) {
            $org = $this->createOrg('Organization');
        }
        $user = UserEntityFactory::new()->asMember()->create(['primaryOrganization' => $org, 'fullName' => $fullName]);
        return $user->_real();
    }

    protected function createOrgAdmin(string $fullName = 'Test User', ?OrganizationEntity $org = null): UserEntity
    {
        if (!$org) {
            $org = $this->createOrg('Organization');
        }
        $user = UserEntityFactory::new()->asOrgAdmin()->create(['primaryOrganization' => $org, 'fullName' => $fullName]);
        return $user->_real();
    }

    protected function createDataAdmin(string $fullName = 'Test User', ?OrganizationEntity $org = null): UserEntity
    {
        if (!$org) {
            $org = $this->createOrg('Organization');
        }
        $user = UserEntityFactory::new()->asDataAdmin()->create(['primaryOrganization' => $org, 'fullName' => $fullName]);
        return $user->_real();
    }

    protected function createSuperAdmin(string $fullName = 'Test User'): UserEntity
    {
        $user = UserEntityFactory::new()->asSuperAdmin()->create(['fullName' => $fullName]);
        return $user->_real();
    }

    protected function createOrg(string $orgName = 'Test Org'): OrganizationEntity
    {
        $org = OrganizationEntityFactory::createOne(['name' => $orgName]);
        return $org->_real();
    }

    protected function createDefaultJob(string $jobName = 'Test Job', ?UserEntity $user = null): Job
    {
        if (!$user) {
            $user = $this->createDefaultUser();
        }
        $job = ESTGenerateFamiliesJobFactory::createOne([
            'jobName' => $jobName,
            'user' => $user,
        ]);
        return $job->_real();
    }

    protected function setJobId(Job $job, int $id): void
    {
        $reflectionProperty = new \ReflectionProperty(\App\Entity\Job::class, 'id');
        $reflectionProperty->setValue($job, $id);
    }
}
