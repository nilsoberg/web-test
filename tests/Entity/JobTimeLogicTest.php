<?php

namespace App\Tests\Entity;

use App\Entity\ESTGenerateBlastJob;
use PHPUnit\Framework\TestCase;

class JobTimeLogicTest extends TestCase
{
    public function testFormattedTimePeriodStates(): void
    {
        $job = new ESTGenerateBlastJob();
        
        // State: Not Started
        $this->assertEquals('Not started', $job->getFormattedTimePeriod());

        // State: In Progress
        $job->setTimeStarted(new \DateTime('-10 minutes'));
        $this->assertEquals('In progress', $job->getFormattedTimePeriod());

        // State: Completed (Calculated)
        $job->setTimeCompleted(new \DateTime('now'));
        $this->assertStringContainsString('minute', $job->getFormattedTimePeriod());

        // State: Invalid Range
        $job->setTimeCompleted(new \DateTime('-20 minutes'));
        $this->assertEquals('Invalid date range', $job->getFormattedTimePeriod());
    }
}
