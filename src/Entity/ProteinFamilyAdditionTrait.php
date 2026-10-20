<?php

namespace App\Entity;

use App\Enum\SequenceType;
use Doctrine\ORM\Mapping as ORM;

trait ProteinFamilyAdditionTrait
{
    #[ORM\Column(name: 'families', type: 'text', length: 65535, nullable: true)]
    private ?string $addFamilies = null;

    #[ORM\Column(nullable: true)]
    private ?int $fraction = null;

    // Results
    #[ORM\Column(nullable: true)]
    private ?int $numFamilyIds = null;

    // Results
    #[ORM\Column(nullable: true)]
    private ?int $numFullFamilyIds = null;

    // Results
    #[ORM\Column(nullable: true)]
    private ?int $numFamilyOverlapIds = null;

    // Results
    #[ORM\Column(nullable: true)]
    private ?int $numFamilyUnirefOverlapIds = null;

    // Results
    #[ORM\Column(nullable: true)]
    private ?int $numFractionFiltered = null;


    public function getAddFamilies(): ?string
    {
        return $this->addFamilies;
    }

    public function setAddFamilies(?string $addFamilies): static
    {
        $this->addFamilies = $addFamilies;

        return $this;
    }

    public function getFraction(): ?int
    {
        return $this->fraction;
    }

    public function setFraction(?int $fraction): static
    {
        $this->fraction = $fraction;

        return $this;
    }

    public function getNumFamilyIds(): ?int
    {
        return $this->numFamilyIds;
    }

    public function setNumFamilyIds(?int $numFamilyIds): static
    {
        $this->numFamilyIds = $numFamilyIds;

        return $this;
    }

    public function getNumFullFamilyIds(): ?int
    {
        return $this->numFullFamilyIds;
    }

    public function setNumFullFamilyIds(?int $numFullFamilyIds): static
    {
        $this->numFullFamilyIds = $numFullFamilyIds;

        return $this;
    }

    public function getNumFamilyOverlapIds(): ?int
    {
        return $this->numFamilyOverlapIds;
    }

    public function setNumFamilyOverlapIds(?int $numFamilyOverlapIds): static
    {
        $this->numFamilyOverlapIds = $numFamilyOverlapIds;

        return $this;
    }

    public function getNumFamilyUnirefOverlapIds(): ?int
    {
        return $this->numFamilyUnirefOverlapIds;
    }

    public function setNumFamilyUnirefOverlapIds(?int $numFamilyUnirefOverlapIds): static
    {
        $this->numFamilyUnirefOverlapIds = $numFamilyUnirefOverlapIds;

        return $this;
    }

    public function getNumFractionFiltered(): ?int
    {
        return $this->numFractionFiltered;
    }

    public function setNumFractionFiltered(?int $numFractionFiltered): static
    {
        $this->numFractionFiltered = $numFractionFiltered;

        return $this;
    }


    /**
     * Virtual property used by the JobSummaryService.
     */
    public function getFormattedUnirefVersion(): string
    {
        return ($this->unirefVersion ?? SequenceType::UniProt)->getLabel();
    }

    /**
     * Virtual property used by JobSummaryService.
     */
    public function getFormattedUnirefVersionWithFamilies(): string
    {
        return $this->getFormattedUnirefVersion();
    }

    /**
     * Virtual property used by the JobSummaryService.
     */
    public function getFormattedFamilies(): string
    {
        if (!$this->addFamilies) {
            return "";
        }
        $families = implode(', ', explode(',', $this->addFamilies));
        return $families;
    }

    /**
     * Virtual property used by the JobSummaryService.
     */
    public function getUseFamilies(): bool
    {
        return $this->addFamilies ? true : false;
    }

    /**
     * Virtual property used by the JobSummaryService.
     */
    public function getNumFamilyIdsUniref(): ?int
    {
        return $this->getNumFamilyIds();
    }
}
