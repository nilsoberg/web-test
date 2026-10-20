<?php

namespace App\Tests;

use App\Entity\Job;

class TestJob extends Job
{
    private ?int $testProperty = null;

    public function getTestProperty(): ?int
    {
        return $this->testProperty;
    }

    public function setTestProperty(?int $testProperty): static
    {
        $this->testProperty = $testProperty;

        return $this;
    }
}
