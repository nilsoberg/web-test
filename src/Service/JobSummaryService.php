<?php

namespace App\Service;

use App\Entity\DisplayName;
use App\Entity\ESTGenerateBlastJob;
use App\Entity\Job;
use DateTimeInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use IntlDateFormatter;

class JobSummaryService
{
    const GENERIC_FALLBACK_JOB = "generic_fallback_job";

    public function __construct(
        private readonly ConditionEvaluator $evaluator,
        private readonly LocalizedDateFormatterService $formatterService,
        private readonly ?LoggerInterface $logger,
        private readonly PropertyAccessorInterface $propertyAccessor,
        private readonly ResultFileService $resultService,
        private readonly RouterInterface $router,
        private readonly array $jobSummaryDefinitions,
        private readonly array $jobTypeToContextMap,
    )
    {
    }

    public function getDisplaySummaryForJob(Job $job, string $viewPurpose = 'default_live'): array
    {
        $contextName = $this->determineJobContext($job, $viewPurpose);
        return $this->getSummaryData($job, $contextName, null);
    }

    public function getDownloadSummaryForJob(Job $job, string $viewPurpose = 'default_live'): array
    {
        $contextName = $this->determineJobContext($job, $viewPurpose);
        return $this->getSummaryData($job, $contextName, 'download');
    }

    private function getSummaryData(Job $job, string $contextName, ?string $displayMode): array
    {
        $visitedContexts = [];
        $resolvedConfig = $this->resolveContextConfiguration($contextName, $visitedContexts);
        $summaryData = [];

        foreach ($resolvedConfig as $fieldConfig) {
            if ($summaryItem = $this->processField($job, $fieldConfig, $displayMode)) {
                $summaryData[] = $summaryItem;
            }
        }

        return $summaryData;
    }

    private function processField(Job $job, array $fieldConfig, ?string $displayMode): ?array
    {
        $fieldDisplayMode = $fieldConfig['display_mode'] ?? null;

        if ($fieldDisplayMode !== null && $fieldDisplayMode !== $displayMode) {
            return null;
        }

        $value = $this->getValueForField($job, $fieldConfig);

        if (!$this->evaluator->checkConditions($job, $fieldConfig, $value)) {
            return null;
        }

        $label = $this->getLabelForField($fieldConfig);

        $summaryItem = [
                'label' => $label,
                'value' => $this->formatValue($value, $fieldConfig, $job),
                'is_html' => $fieldConfig['is_html'] ?? false,
                'help' => $fieldConfig['help'] ?? '',
            ];

        return $summaryItem;
    }

    private function getLabelForField(array $fieldConfig): string
    {
        //if (isset($fieldConfig['label_trans_key']) && $this->translator) {
        //    return $this->translator->trans($fieldConfig['label_trans_key']);
        //}

        if (isset($fieldConfig['label'])) {
            return $fieldConfig['label'];
        }

        $property = $fieldConfig['property'];
        $basename = basename(str_replace('.', '_', $property));
        return ucfirst(str_replace('_', ' ', $basename));
    }

    private function getValueForField(Job $job, array $fieldConfig): mixed
    {
        $value = null;
        try {
            if ($this->propertyAccessor->isReadable($job, $fieldConfig['property'])) {
                $value = $this->propertyAccessor->getValue($job, $fieldConfig['property']);
            }
        } catch (\Throwable $e) {
            $this->logger->error('Property access failed for job summary.', [
                'property' => $fieldConfig['property'],
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return $value ?? $fieldConfig['default'] ?? null;
    }




    ////////////////////////////////////////////////////////////////////////////////////////////////
    //
    // Context Methods
    //

    private function determineJobContext(Job $job, string $viewPurpose): string
    {
        $type = DisplayName::getFrom($job);
        if (isset($this->jobTypeToContextMap[$type]))
        {
            if (isset($this->jobTypeToContextMap[$type][$viewPurpose]))
            {
                return $this->jobTypeToContextMap[$type][$viewPurpose];
            }
            else
            {
                return $this->jobTypeToContextMap[self::GENERIC_FALLBACK_JOB][$viewPurpose];
            }
        }
        else
        {
            return $this->jobTypeToContextMap[self::GENERIC_FALLBACK_JOB][$viewPurpose];
        }
    }

    /**
     * Resolves the full field configuration for a given context.
     *
     * @param string $contextName The name of the context to resolve.
     * @param array  $visited     An array to keep track of visited contexts for circular dependency detection.
     * @return array The resolved list of field definitions.
     */
    private function resolveContextConfiguration(string $contextName, array &$visited = []): array
    {
        $this->guardAgainstCircularDependency($contextName, $visited);
        $config = $this->getContextDefinition($contextName);

        $resolvedFieldsMap = [];

        // 1. Load fields from parent contexts (if any)
        $this->loadParentFields($config, $resolvedFieldsMap, $visited);

        // 2. Load fields defined directly in the current context
        $this->loadCurrentContextFields($config, $resolvedFieldsMap);

        // Clean up visited stack for this path after successful resolution
        unset($visited[$contextName]);

        // 3. Sort the combined fields
        return $this->sortFields($resolvedFieldsMap);
    }

    /**
     * Checks for and throws an exception if a circular dependency is detected.
     */
    private function guardAgainstCircularDependency(string $contextName, array &$visited): void
    {
        if (isset($visited[$contextName]))
        {
            $path = implode(' -> ', array_keys($visited)) . " -> {$contextName}";
            // Clean up visited before throwing to avoid polluting subsequent unrelated calls
            // This specific path has failed, so the current $visited state is for this error.
            throw new \LogicException("Circular extension detected involving summary context: {$contextName}. Path: {$path}");
        }
        $visited[$contextName] = true; // Mark as visited for the current resolution path
    }

    /**
     * Retrieves the raw definition for a given context name.
     */
    private function getContextDefinition(string $contextName): array|string // string if it's a direct list of fields
    {
        if (!isset($this->jobSummaryDefinitions[$contextName]))
        {
            $this->logger->error('Undefined summary context.', [
                'context' => $contextName,
            ]);
            throw new \InvalidArgumentException("Undefined summary context: {$contextName}");
        }
        return $this->jobSummaryDefinitions[$contextName];
    }

    /**
     * Loads fields from parent contexts specified in _extends.
     *
     * @param array|string $currentConfig     The configuration of the current context.
     * @param array        &$resolvedFieldsMap The map to populate with parent fields.
     * @param array        &$visited          The array for circular dependency tracking.
     */
    private function loadParentFields($currentConfig, array &$resolvedFieldsMap, array &$visited): void
    {
        // Only process if this config has a parent (e.g. '_extents')
        if (!is_array($currentConfig) || !isset($currentConfig['_extends']))
        {
            return;
        }

        $parentContextNames = (array) $currentConfig['_extends']; // Handles single or multiple extends

        foreach ($parentContextNames as $parentContextName)
        {
            if (empty($parentContextName) || !is_string($parentContextName))
            {
                continue;
            }

            // Recursively resolve parent.
            $parentFields = $this->resolveContextConfiguration($parentContextName, $visited);
            foreach ($parentFields as $field)
            {
                if (isset($field['property']))
                {
                    // Fields from parents processed later in the _extends list will override earlier ones.
                    $resolvedFieldsMap[$field['property']] = $field;
                }
            }
        }
    }

    /**
     * Loads fields defined directly within the current context's configuration.
     *
     * @param array|string $currentConfig     The configuration of the current context.
     * @param array        &$resolvedFieldsMap The map to populate/override with current fields.
     */
    private function loadCurrentContextFields($currentConfig, array &$resolvedFieldsMap): void
    {
        $currentContextSpecificFields = [];

        // If $currentConfig is not an array (e.g., a string that was a context name but didn't resolve),
        // $currentContextSpecificFields will remain empty, which is fine.
        if (!is_array($currentConfig))
        {
            return;
        }

        if (isset($currentConfig['fields']) && is_array($currentConfig['fields']))
        {
            $currentContextSpecificFields = $currentConfig['fields'];
        }

        // If it extends but has no fields it is a pure inheritance wrapper
        else if (!isset($currentConfig['_extends']))
        {
            $currentContextSpecificFields = $currentConfig;
        }

        foreach ($currentContextSpecificFields as $fieldDefinition)
        {
            if (is_array($fieldDefinition) && isset($fieldDefinition['property']))
            {
                // Add or override field in the map. Current context's fields take highest precedence.
                $resolvedFieldsMap[$fieldDefinition['property']] = $fieldDefinition;
            }
        }
    }

    /**
     * Sorts the resolved fields map by the 'order' key.
     *
     * @param array $resolvedFieldsMap The map of fields to sort.
     * @return array A numerically indexed array of sorted field definitions.
     */
    private function sortFields(array $resolvedFieldsMap): array
    {
        if (empty($resolvedFieldsMap))
        {
            return [];
        }

        uasort($resolvedFieldsMap, function ($a, $b) {
            $orderA = $a['order'] ?? PHP_INT_MAX; // Fields without 'order' go to the end
            $orderB = $b['order'] ?? PHP_INT_MAX;
            if ($orderA == $orderB) {
                return 0;
            }
            return ($orderA < $orderB) ? -1 : 1;
        });

        return array_values($resolvedFieldsMap);
    }




    ////////////////////////////////////////////////////////////////////////////////////////////////
    //
    // Formatting Methods
    //

    private function formatValue($value, array $fieldConfig, Job $job)
    {
        if ($value === null)
        {
            return $fieldConfig['default'] ?? ''; // Or null, depending on how you want to handle it
        }

        $formatter = $fieldConfig['formatter'] ?? null;

        switch ($formatter)
        {
            case 'datetime':
                if ($value instanceof DateTimeInterface)
                {
                    return $this->formatterService->format(
                        $value,
                        skeleton: $fieldConfig['skeleton'] ?? null,
                        dateStyle: $fieldConfig['date_style'] ?? 'medium',
                        timeStyle: $fieldConfig['time_style'] ?? 'short',
                        pattern: $fieldConfig['pattern'] ?? null,
                    );
                }
                return (string) $value; // Fallback
            case 'boolean_yes_no':
                return $value ? 'Yes' : 'No';
            case 'boolean_on_off':
                return $value ? 'On' : 'Off';
            case 'job_status':
            case 'job_status':
                return ucfirst(strtolower(str_replace('_', ' ', (string) $value)));
            case 'parent_job_id':
                $parentId = $job->getParentJob()->getId();
                $params = ['id' => $parentId, 'key' => $job->getParentJob()->getUuid()];
                $url = $this->router->generate($fieldConfig['formatter_options']['route_name'], $params);
                $link = sprintf('<a href="%s">%s</a>', $url, $parentId);
                return $link;
            case 'job_results_link':
                $params = ['id' => $job->getId(), 'key' => $job->getUuid()];
                $url = $this->router->generate($fieldConfig['formatter_options']['route_name'], $params, UrlGeneratorInterface::ABSOLUTE_URL);
                return $url;
            case 'blast_query':
                $query = $this->getBlastQueryFromJob($job);
                return $query;
            case 'blast_query_link':
                if (!isset($fieldConfig['formatter_options']['route_name'])) {
                    return 'Download Sequence';
                }
                $params = ['id' => $job->getId(), 'key' => $job->getUuid()];
                $url = $this->router->generate($fieldConfig['formatter_options']['route_name'], $params);
                $link = sprintf('<a href="%s">%s</a>', $url, 'Download sequence');
                return $link;
            case 'integer':
                return number_format($value);
            case 'number':
                return number_format($value, 3);
            case 'pid_threshold_list':
                return implode(', ', array_map(fn($val) => $val . '%', $value));
            case 'db_metadata':
                return $job->getDbMetadata()?->getPublicName();
            default:
                if (is_array($value) || is_object($value) && !$value instanceof DateTimeInterface)
                {
                    return json_encode($value); // Or some other string representation
                }
                return (string) $value;
        }
    }

    private function getBlastQueryFromJob(Job $job): string
    {
        if (!($job instanceof ESTGenerateBlastJob)) {
            return "";
        }

        $resultsDir = $this->resultService->getResultsDirectory($job);
        $queryPath = $resultsDir . "/" . $job->getJobFilename();
        if (file_exists($queryPath)) {
            try {
                $query = file_get_contents($queryPath);
                return wordwrap($query, 80, "\t", true);
            } catch (\Throwable $e) {
                $this->logger->error('Failed to get BLAST query sequence from file.', [
                    'job_filename' => $queryPath,
                    'error' => $e->getMessage(),
                ]);
                return "";
            }
        } else {
            $this->logger->error('Failed to get BLAST query sequence from file; file does not exist.', [
                'job_filename' => $queryPath,
            ]);
            return "";
        }
    }
}
