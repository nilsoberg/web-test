<?php

namespace App\Service;

use App\Entity\Job;
use Psr\Log\LoggerInterface;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

class ConditionEvaluator
{
    public function __construct(
        private PropertyAccessorInterface $propertyAccessor,
        private ?LoggerInterface $logger
    )
    {
    }

    public function checkConditions(Job $job, array $fieldConfig, mixed $value): bool
    {
        // Find all keys like 'condition_all', 'condition_any', etc.
        $conditionKeys = array_keys(array_filter(
            $fieldConfig,
            fn($key) => str_starts_with($key, 'condition_'),
            ARRAY_FILTER_USE_KEY
        ));

        // If no condition keys exist, the check passes.
        if (empty($conditionKeys)) {
            return true;
        }

        foreach ($conditionKeys as $key) {
            $conditionValue = $fieldConfig[$key];
            $result = match ($key) {
                'condition_all' => $this->evaluateConditionAll($job, $conditionValue),
                'condition_any' => $this->evaluateConditionAny($job, $conditionValue),
                'condition_is_true', 'condition_is_not_empty' => $this->evaluateShortcutCondition($job, $key, $fieldConfig, $value),
                default => true,
            };

            if (!$result) {
                return false;
            }
        }

        return true;
    }

    private function evaluateConditionAll(Job $job, mixed $conditions): bool
    {
        if (empty($conditions) || !is_array($conditions)) {
            return true;
        }

        foreach ($conditions as $condition) {
            if (!is_array($condition) || !$this->evaluateCondition($job, $condition)) {
                return false; // One failure means the whole set fails.
            }
        }

        return true;
    }

    private function evaluateConditionAny(Job $job, mixed $conditions): bool
    {
        if (empty($conditions) || !is_array($conditions)) {
            return false; // An empty list of 'any' conditions fails.
        }

        foreach ($conditions as $condition) {
            if (is_array($condition) && $this->evaluateCondition($job, $condition)) {
                return true; // One success means the whole set passes.
            }
        }
        return false;
    }

    // Conditions like 'condition_is_true' as a shortcut
    private function evaluateShortcutCondition(Job $job, string $conditionKey, array $fieldConfig, mixed $value): bool
    {
        return match($conditionKey) {
            'condition_is_true' => $value === true,
            // Using !empty() checks for null, false, 0, "", and [].
            'condition_is_not_empty' => !empty($value),
            default => false,
        };
    }

    /**
     * Evaluates a single condition object against the Job.
     *
     * @param Job   $job       The job entity.
     * @param array $condition The condition definition from YAML.
     * @return bool True if the condition is met, false otherwise.
     */
    private function evaluateCondition(Job $job, array $condition): bool
    {
        if (!isset($condition['property'])) {
            $this->logger->warning('Malformed condition in summary definition: missing "property".', ['condition' => $condition]);
            return false; // Malformed condition, treat as not met
        }

        $actualValue = null;
        $propertyIsReadable = false;
        try
        {
            if ($this->propertyAccessor->isReadable($job, $condition['property']))
            {
                $actualValue = $this->propertyAccessor->getValue($job, $condition['property']);
                $propertyIsReadable = true;
            }
        }
        catch (\Throwable $e)
        {
            $this->logger->error('Property access failed for job summary in condition.', [
                'property' => $condition['property'],
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(), // Full trace for debugging
			]);
            // Property access error (e.g., path invalid)
            // $this->logger->debug("Error accessing property '{$condition['property']}' for condition evaluation.", ['exception' => $e]);
            // If property isn't readable, it often means condition related to it can't be met,
            // unless the condition is specifically 'is_null' or 'is_empty' on an uninitialized path.
            // For simplicity here, if not readable, most checks below will fail gracefully or evaluate based on $actualValue being null.
        }

        if (!$propertyIsReadable && !(isset($condition['is_null']) || isset($condition['is_empty'])))
        {
             // If property isn't readable and the check isn't specifically for null/empty, assume condition fails.
             // This handles cases where a path like 'nonExistentRelation.property' is used.
            return false;
        }

        return $this->compareCondition($condition, $actualValue);
    }

    private function compareCondition(array $condition, $actualValue): bool
    {
        if (array_key_exists('is_value', $condition))
        {
            return $actualValue === $condition['is_value'];
        }
        if (array_key_exists('is_not_value', $condition))
        {
            return $actualValue !== $condition['is_not_value'];
        }
        if (isset($condition['is_true'])) // Value of is_true itself doesn't matter, its presence does
        {
            return (bool)$actualValue === true;
        }
        if (isset($condition['is_false']))
        {
            return (bool)$actualValue === false;
        }
        if (isset($condition['is_not_empty']))
        {
            return !empty($actualValue);
        }
        if (isset($condition['is_empty']))
        {
            return empty($actualValue);
        }
        if (isset($condition['is_null']))
        {
            return $actualValue === null;
        }
        if (isset($condition['is_not_null']))
        {
            return $actualValue !== null;
        }
        if (array_key_exists('is_greater_than', $condition))
        {
            return is_numeric($actualValue) && is_numeric($condition['is_greater_than']) && $actualValue > $condition['is_greater_than'];
        }
        if (array_key_exists('is_less_than', $condition))
        {
            return is_numeric($actualValue) && is_numeric($condition['is_less_than']) && $actualValue < $condition['is_less_than'];
        }
        if (array_key_exists('in_array', $condition) && is_array($condition['in_array']))
        {
            return in_array($actualValue, $condition['in_array'], true); // Strict comparison
        }
        if (array_key_exists('not_in_array', $condition) && is_array($condition['not_in_array']))
        {
            return !in_array($actualValue, $condition['not_in_array'], true); // Strict comparison
        }

        return false; // Default to false if no known condition operator is found
    }
}
