<?php

namespace App\Tests\Service;

use App\Auth\Entity\UserEntity;
use App\Entity\Job;
use App\Service\ConditionEvaluator;
use App\Service\JobSummaryService;
use App\Service\LocalizedDateFormatterService;
use App\Service\ResultFileService;
use App\Tests\BaseServiceTestCase;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

class JobSummaryServiceTest extends BaseServiceTestCase
{
    private $service;
    private $propertyAccessor;
    private $conditionEvaluator;
    private $router;
	private $security;
    private $requestStack;
    private $defaultTimezone;
    private $dateFormatter;
    private $logger;
    private $resultService;

    /**
     * This mimics the structure of 'job_summary_definitions.yaml'
     * Case 1: Mixins (start with _) are direct arrays of fields.
     * Case 2: Concrete contexts have '_extends' and 'fields'.
     */
    private array $testDefinitions = [
        // Case 1: Mixin (Direct list of fields, no 'fields' wrapper)
        '_mixin_base' => [
            ['property' => 'id', 'label' => 'ID', 'order' => 10],
            ['property' => 'status', 'label' => 'Base Status', 'order' => 20],
        ],

        // Case 1b: Another Mixin
        '_mixin_date' => [
             ['property' => 'created_at', 'label' => 'Date Created', 'order' => 30],
        ],

        // Case 2: Concrete Context (Uses _extends and fields)
        'concrete_context' => [
            '_extends' => [
                '_mixin_base',
                '_mixin_date'
            ],
            'fields' => [
                ['property' => 'description', 'label' => 'Description', 'order' => 40],
            ]
        ],

        // Case 3: Override (Child field overrides Parent field by property name)
        'override_context' => [
            '_extends' => ['_mixin_base'],
            'fields' => [
                // Overriding 'status' from _mixin_base
                ['property' => 'status', 'label' => 'Overridden Status', 'order' => 20],
            ]
        ],

        // Circular Dependencies
        'circular_a' => [
            '_extends' => ['circular_b']
        ],
        'circular_b' => [
            '_extends' => ['circular_a']
        ],

        // Formatting Context
        'formatting_context' => [
            // Concrete context without extends is also valid, usually has 'fields' key if it follows that pattern,
            // or could be a direct list. Your YAML mostly uses _extends for concrete ones.
            // Let's use the explicit 'fields' style for a concrete one.
            'fields' => [
                ['property' => 'is_public', 'formatter' => 'boolean_yes_no', 'order' => 1],
            ]
        ]
    ];

    private array $testContextMap = [
        JobSummaryService::GENERIC_FALLBACK_JOB => [
            'default_live' => 'concrete_context' // Default for test
        ]
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->propertyAccessor = $this->createMock(PropertyAccessorInterface::class);
        $this->conditionEvaluator = $this->createMock(ConditionEvaluator::class);
        $this->router = $this->createMock(RouterInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->resultService = $this->container->get(ResultFileService::class);

		$this->security = $this->createMock(Security::class);
        $this->requestStack = $this->createMock(RequestStack::class);

        // Default: No user logged in
        $this->security->method('getUser')->willReturn(null);
        
        // Default: Request exists with locale 'en'
        $request = $this->createMock(Request::class);
        $request->method('getLocale')->willReturn('en');
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        // Default: Property Accessor can read everything
        $this->propertyAccessor->method('isReadable')->willReturn(true);

        // Default: Conditions always pass
        $this->conditionEvaluator->method('checkConditions')->willReturn(true);

        $this->defaultTimezone = 'America/Chicago';
        $this->dateFormatter = new LocalizedDateFormatterService($this->requestStack, $this->security, $this->defaultTimezone);

        $this->service = new JobSummaryService(
            $this->conditionEvaluator,
            $this->dateFormatter,
            $this->logger,
            $this->propertyAccessor,
            $this->resultService,
            $this->router,
            $this->testDefinitions, // Inject our structured test config
            $this->testContextMap,
        );
    }

    public function testInheritanceMergesFieldsFromMixins(): void
    {
        // We use Reflection to access the private method 'getSummaryData'
        // This tests that 'concrete_context' correctly grabs fields from '_mixin_base' and '_mixin_date'

        $job = $this->createMock(Job::class);

        $data = $this->invokeMethod($this->service, 'getSummaryData', [$job, 'concrete_context', null]);

        // Expect 4 fields: ID, Status (from base), Created (from date), Description (from concrete)
        $this->assertCount(4, $data);

        // Verify keys and order
        $this->assertEquals('ID', $data[0]['label']);
        $this->assertEquals('Base Status', $data[1]['label']);
        $this->assertEquals('Date Created', $data[2]['label']);
        $this->assertEquals('Description', $data[3]['label']);
    }

    public function testOverrideReplacesFieldDefinition(): void
    {
        $job = $this->createMock(Job::class);

        // 'override_context' extends '_mixin_base' but redefines 'status'
        $data = $this->invokeMethod($this->service, 'getSummaryData', [$job, 'override_context', null]);

        // Should have 2 fields (ID and Status)
        $this->assertCount(2, $data);

        // Verify that ID is untouched but Status has the new label
        $this->assertEquals('ID', $data[0]['label']);
        $this->assertEquals('Overridden Status', $data[1]['label']); 
    }

    public function testCircularDependencyThrowsException(): void
    {
        $job = $this->createMock(Job::class);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Circular extension detected');

        $this->invokeMethod($this->service, 'getSummaryData', [$job, 'circular_a', null]);
    }

    public function testFormattingBooleanYesNo(): void
    {
        $job = $this->createMock(Job::class);

        // Mock PropertyAccessor to return TRUE for 'is_public'
        $this->propertyAccessor->expects($this->once())
            ->method('getValue')
            ->with($job, 'is_public')
            ->willReturn(true);

        $data = $this->invokeMethod($this->service, 'getSummaryData', [$job, 'formatting_context', null]);

        $this->assertCount(1, $data);
        $this->assertEquals('Yes', $data[0]['value']);
    }

    public function testConditionEvaluatorCanFilterFields(): void
    {
        $job = $this->createMock(Job::class);

        // Make a condition evaluator that will always fail
        $failingEvaluator = $this->createMock(ConditionEvaluator::class);
        $failingEvaluator->expects($this->any())
            ->method('checkConditions')
            ->willReturn(false);

        // Set a filtering definition that has a condition so we can test if the checkConditions method
        // is properly applied in the service
        $filteringDefinitions = array_merge($this->testDefinitions, [
            'filtered_context' => [
                'fields' => [
                    // We add a dummy condition. The Mock will ignore the key name and just return false.
                    ['property' => 'id', 'label' => 'ID', 'condition_must_fail' => true] 
                ]
            ]
        ]);

        // Re-create the service with this specific definition
        $service = new JobSummaryService(
            $failingEvaluator,
            $this->dateFormatter,
            $this->createMock(LoggerInterface::class),
            $this->propertyAccessor,
            $this->resultService,
            $this->router,
            $filteringDefinitions,
            $this->testContextMap,
        );

        $data = $this->invokeMethod($service, 'getSummaryData', [$job, 'filtered_context', null]);

        // Since conditions fail, result should be empty
        $this->assertEmpty($data, 'Data should be empty because the condition evaluator returned false.');
    }

    // ----- Helpers -----

    private function invokeMethod(object $object, string $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        return $method->invokeArgs($object, $parameters);
    }
}
