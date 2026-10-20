<?php

namespace App\Entity;

use App\Enum\SequenceType;
use Doctrine\ORM\Mapping as ORM;

// Output values
trait ColorSSNTrait
{
    // Results
    #[ORM\Column(nullable: true)]
    private ?int $numSsnClusters = null;

    // Results
    #[ORM\Column(nullable: true)]
    private ?int $numSsnSingletons = null;

    // Results
    #[ORM\Column(nullable: true)]
    private ?int $numSsnMetanodes = null;

    // Results
    #[ORM\Column(nullable: true)]
    private ?int $numSsnAccessionIds = null;

    // Results
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $colorSsnSequenceSource = null;


    public function getNumSsnClusters(): ?int
    {
        return $this->numSsnClusters;
    }

    public function setNumSsnClusters(?int $numSsnClusters): static
    {
        $this->numSsnClusters = $numSsnClusters;

        return $this;
    }

    public function getNumSsnSingletons(): ?int
    {
        return $this->numSsnSingletons;
    }

    public function setNumSsnSingletons(?int $numSsnSingletons): static
    {
        $this->numSsnSingletons = $numSsnSingletons;

        return $this;
    }

    public function getNumSsnMetanodes(): ?int
    {
        return $this->numSsnMetanodes;
    }

    public function setNumSsnMetanodes(?int $numSsnMetanodes): static
    {
        $this->numSsnMetanodes = $numSsnMetanodes;

        return $this;
    }

    public function getNumSsnAccessionIds(): ?int
    {
        return $this->numSsnAccessionIds;
    }

    public function setNumSsnAccessionIds(?int $numSsnAccessionIds): static
    {
        $this->numSsnAccessionIds = $numSsnAccessionIds;

        return $this;
    }

    public function getColorSsnSequenceSource(): ?string
    {
        return $this->colorSsnSequenceSource;
    }

    public function setColorSsnSequenceSource(?string $colorSsnSequenceSource): static
    {
        $this->colorSsnSequenceSource = $colorSsnSequenceSource;

        return $this;
    }
}
