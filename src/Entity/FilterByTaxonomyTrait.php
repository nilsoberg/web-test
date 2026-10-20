<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

trait FilterByTaxonomyTrait
{
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $taxSearch = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $taxSearchName = null;

    // Results
    #[ORM\Column(nullable: true)]
    private ?int $numTaxonomyFiltered = null;


    public function getTaxSearch(): ?array
    {
        $data = $this->taxSearch ?? [];

        if ($data !== null && $this->taxSearchName !== null) {
            $data['predefined'] = $this->taxSearchName;
        }

        return empty($data) ? null : $data;
    }

    public function setTaxSearch(?array $data): static
    {
        if ($data === null) {
            $this->taxSearchName = null;
            $this->taxSearch = null;
            return $this;
        }

        // Extract and save the predefined name to the separate column
        if (!empty($data['predefined'])) {
            $this->taxSearchName = $data['predefined'];
        } else {
            $this->taxSearchName = null;
        }

        // Remove 'predefined' from the JSON
        unset($data['predefined']);

        // If a predefined filter was selected, then don't save the conditions
        if ($this->taxSearchName !== null) {
            $this->taxSearch = null;
        } else {
            if (!empty($data['conditions'])) {
                $data['conditions'] = array_values($data['conditions']);
            }

            // Save the remaining rules to the JSON column (or null if empty)
            $this->taxSearch = empty($data['conditions']) ? null : $data;
        }

        return $this;
    }

    public function getTaxSearchName(): ?string
    {
        return $this->taxSearchName;
    }

    public function setTaxSearchName(?string $taxSearchName): static
    {
        $this->taxSearchName = $taxSearchName;

        return $this;
    }

    public function getNumTaxonomyFiltered(): ?int
    {
        return $this->numTaxonomyFiltered;
    }

    public function setNumTaxonomyFiltered(?int $numTaxonomyFiltered): static
    {
        $this->numTaxonomyFiltered = $numTaxonomyFiltered;

        return $this;
    }

    // Return the predefined search name (taxSearchName) if it is set, otherwise return the
    // user-defined conditions (taxSearch)
    public function getFormattedTaxSearch(): string
    {
        if (!$this->taxSearch && !$this->taxSearchName) {
            return '';
        }

        // Return the predefined search name if one is specified
        if ($this->taxSearchName) {
            return $this->taxSearchName;
        }

        if ($this->taxSearch) {
            $taxRows = [];
            foreach ($this->taxSearch['conditions'] as $num => $condition) {
                $taxRows[] = implode(' ', [$condition['field'], $condition['operator'], $condition['value']]);
            }

            $conditions = implode(', ', $taxRows);
            $operator = $this->taxSearch['operator'];
            return "{$conditions} ($operator)";
        } else {
            return '';
        }
    }
}
