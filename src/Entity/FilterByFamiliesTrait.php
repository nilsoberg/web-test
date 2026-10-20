<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

trait FilterByFamiliesTrait
{
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $filterByFamilies = null;

    // Results
    #[ORM\Column(nullable: true)]
    private ?int $numFamilyFiltered = null;


    public function getFilterByFamilies(): ?string
    {
        return $this->filterByFamilies;
    }

    public function setFilterByFamilies(?string $filterByFamilies): static
    {
        $this->filterByFamilies = $filterByFamilies;

        return $this;
    }

    public function getNumFamilyFiltered(): ?int
    {
        return $this->numFamilyFiltered;
    }

    public function setNumFamilyFiltered(?int $numFamilyFiltered): static
    {
        $this->numFamilyFiltered = $numFamilyFiltered;

        return $this;
    }
}
