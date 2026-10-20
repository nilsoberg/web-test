<?php

namespace App\Tests\Service;

use App\Tests\TestJob;
use App\Service\ConditionEvaluator;
use App\Tests\BaseServiceTestCase;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;

class ConditionEvaluatorTest extends BaseServiceTestCase
{
    private ConditionEvaluator $evaluator;

    protected function setUp(): void
    {
        parent::setUp();
        $accessor = PropertyAccess::createPropertyAccessor();
        $logger = $this->createMock(LoggerInterface::class);
        $this->evaluator = new ConditionEvaluator($accessor, $logger);
    }

    public function testSimpleComparison(): void
    {
        $job = new TestJob();

        // Use reflection to set private ID since we aren't using DB
        $this->setJobProperty($job, 'id', 50);
        $this->setJobProperty($job, 'testProperty', 42);

        $config = ['condition_1' => ['property' => 'id', 'is_value' => 50]];
        $this->assertTrue($this->evaluator->checkConditions($job, $config, null));
    }

    // Helper to set private properties without setters
    private function setJobProperty(object $job, string $property, mixed $value): void
    {
        $refl = new \ReflectionClass($job);
        // Walk up parent classes if property is private in parent
        while (!$refl->hasProperty($property)) {
            $refl = $refl->getParentClass();
            if (!$refl) return;
        }

        $prop = $refl->getProperty($property);
        $prop->setAccessible(true);
        $prop->setValue($job, $value);
    }
}
