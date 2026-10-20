<?php

namespace App\Tests\Entity;

use App\Entity\FilterByTaxonomyTrait;
use PHPUnit\Framework\TestCase;

class FilterByTaxonomyTraitTest extends TestCase
{
    private function getTraitObject()
    {
        return new class {
            use FilterByTaxonomyTrait;
        };
    }

    public function testSetTaxSearchExtractsPresetAndDoesNotSetConditions(): void
    {
        $entity = $this->getTraitObject();

        // Simulate the array coming from the Symfony Form submission
        $submittedData = [
            'predefined' => 'fungi',
            'operator' => 'OR',
            'conditions' => [
                ['rank' => 'phylum', 'operator' => '=', 'value' => 'Ascomycota']
            ]
        ];

        $entity->setTaxSearch($submittedData);

        // Assert the preset string was extracted to the specific property
        $this->assertEquals('fungi', $entity->getTaxSearchName());

        // Assert the raw property does not contain 'predefined' anymore
        // (Use Reflection to check the private property exactly as Doctrine would save it)
        $reflection = new \ReflectionClass($entity);
        $property = $reflection->getProperty('taxSearch');
        $property->setAccessible(true);
        $rawData = $property->getValue($entity);

        $this->assertNull($rawData);

        // Assert that getTaxSearch() re-injects the preset for the form to use
        $rebuiltData = $entity->getTaxSearch();
        $this->assertArrayHasKey('predefined', $rebuiltData);
        $this->assertArrayNotHasKey('conditions', $rebuiltData);
    }

    public function testSetTaxSearchSetsConditionsAndPredefinedNull(): void
    {
        $entity = $this->getTraitObject();

        // Simulate the array coming from the Symfony Form submission
        $submittedData = [
            'predefined' => null,
            'operator' => 'OR',
            'conditions' => [
                ['rank' => 'phylum', 'operator' => '=', 'value' => 'Ascomycota']
            ]
        ];

        $entity->setTaxSearch($submittedData);

        // Assert the preset string was extracted to the specific property
        $this->assertNull($entity->getTaxSearchName());

        // Assert the raw property does not contain 'predefined' anymore
        // (Use Reflection to check the private property exactly as Doctrine would save it)
        $reflection = new \ReflectionClass($entity);
        $property = $reflection->getProperty('taxSearch');
        $property->setAccessible(true);
        $rawData = $property->getValue($entity);

        $this->assertArrayNotHasKey('predefined', $rawData);
        $this->assertEquals('OR', $rawData['operator']);
    }

    public function testSetTaxSearchWithEmptyConditionsSavesNull(): void
    {
        $entity = $this->getTraitObject();

        // Simulate a form submission where a preset was selected, but all rows were deleted
        $submittedData = [
            'predefined' => 'eukaryota_no_fungi',
            'operator' => 'AND',
            'conditions' => [] 
        ];

        $entity->setTaxSearch($submittedData);

        // The preset should still be recorded
        $this->assertEquals('eukaryota_no_fungi', $entity->getTaxSearchName());

        // But the JSON column should be strictly null to keep the DB clean
        $reflection = new \ReflectionClass($entity);
        $property = $reflection->getProperty('taxSearch');
        $property->setAccessible(true);

        $this->assertNull($property->getValue($entity));
    }

    public function testSetTaxSearchWithNullResetsData(): void
    {
        $entity = $this->getTraitObject();
        $entity->setTaxSearchName('old_preset');

        // Simulate passing null (e.g., clearing the form entirely)
        $entity->setTaxSearch(null);

        $this->assertNull($entity->getTaxSearchName());
        $this->assertNull($entity->getTaxSearch());
    }

    public function testGetFormattedCustomTaxSearch(): void
    {
        $entity = $this->getTraitObject();

        // Simulate a form submission where custom filters were specified
        $submittedData = [
            'predefined' => '',
            'operator' => 'AND',
            'conditions' => [['field' => 'domain', 'operator' => '=', 'value' => 'Bacteria']],
        ];

        $entity->setTaxSearch($submittedData);

        $formattedSearch = $entity->getFormattedTaxSearch();
        $this->assertEquals('domain = Bacteria (AND)', $formattedSearch);
    }

    public function testGetFormattedTaxSearchName(): void
    {
        $entity = $this->getTraitObject();

        // Simulate a form submission where a preset was selected
        $submittedData = [
            'predefined' => 'eukaryota_no_fungi',
            'operator' => 'AND',
            'conditions' => [['rank' => 'domain', 'operator' => '=', 'value' => 'Bacteria']],
        ];

        $entity->setTaxSearch($submittedData);

        $formattedSearch = $entity->getFormattedTaxSearch();
        $this->assertEquals('eukaryota_no_fungi', $formattedSearch);
    }

    public function testGetFormattedTaxSearchNoFilter(): void
    {
        $entity = $this->getTraitObject();

        // Simulate a form submission where no filters are defined
        $submittedData = [
            'predefined' => '',
            'operator' => 'AND',
            'conditions' => [],
        ];

        $entity->setTaxSearch($submittedData);

        $formattedSearch = $entity->getFormattedTaxSearch();
        $this->assertEquals('', $formattedSearch);
    }
}
