<?php

namespace App\Tests\Factory;

use App\Entity\ESTGenerateFamiliesJob;

final class ESTGenerateFamiliesJobFactory extends JobFactory
{
    public static function class(): string
    {
        return ESTGenerateFamiliesJob::class;
    }

    protected function defaults(): array|callable
    {
        return array_merge(parent::defaults(), [
            // From ExcludeFragmentsTrait
            'excludeFragments' => self::faker()->boolean(),
            'addFamilies' => 'PF05544',
            'numFamilyIds' => self::faker()->numberBetween(100, 5000),
        ]);
    }

    protected function initialize(): static
    {
        return $this->afterPersist(function(ESTGenerateFamiliesJob $job) {
           $this->initializeJobDir($job);
        });
    }
}
