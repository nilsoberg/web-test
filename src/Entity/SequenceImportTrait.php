<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

trait SequenceImportTrait
{
    // Results
    #[ORM\Column(nullable: true)]
    private ?int $numComputedIds = null;

    // Results
    #[ORM\Column(nullable: true)]
    private ?int $numImportedIds = null;

    // Results
    #[ORM\Column(nullable: true)]
    private ?float $outputConvergenceRatio = null;

    // Results
    #[ORM\Column(nullable: true)]
    private ?int $numBlastEdges = null;


    public function getNumComputedIds(): ?int
    {
        return $this->numComputedIds;
    }

    public function setNumComputedIds(?int $numComputedIds): static
    {
        $this->numComputedIds = $numComputedIds;

        return $this;
    }

    public function getNumImportedIds(): ?int
    {
        return $this->numImportedIds;
    }

    public function setNumImportedIds(?int $numImportedIds): static
    {
        $this->numImportedIds = $numImportedIds;

        return $this;
    }

    public function getOutputConvergenceRatio(): ?float
    {
        return $this->outputConvergenceRatio;
    }

    public function setOutputConvergenceRatio(?float $outputConvergenceRatio): static
    {
        $this->outputConvergenceRatio = $outputConvergenceRatio;

        return $this;
    }

    public function getNumBlastEdges(): ?int
    {
        return $this->numBlastEdges;
    }

    public function setNumBlastEdges(?int $numBlastEdges): static
    {
        $this->numBlastEdges = $numBlastEdges;

        return $this;
    }
}
