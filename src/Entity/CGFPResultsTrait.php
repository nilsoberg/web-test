<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

trait CGFPResultsTrait
{
    // Results
    #[ORM\Column(nullable: true)]
    private ?int $numCdhitClusters = null;

    // Results
    #[ORM\Column(nullable: true)]
    private ?int $numMarkers = null;

    // Results
    #[ORM\Column(nullable: true)]
    private ?int $numUniqueSeq = null;


    public function getNumCdhitClusters(): ?int
    {
        return $this->numCdhitClusters;
    }

    public function setNumCdhitClusters(?int $numCdhitClusters): static
    {
        $this->numCdhitClusters = $numCdhitClusters;

        return $this;
    }

    public function getNumMarkers(): ?int
    {
        return $this->numMarkers;
    }

    public function setNumMarkers(?int $numMarkers): static
    {
        $this->numMarkers = $numMarkers;

        return $this;
    }

    public function getNumUniqueSeq(): ?int
    {
        return $this->numUniqueSeq;
    }

    public function setNumUniqueSeq(?int $numUniqueSeq): static
    {
        $this->numUniqueSeq = $numUniqueSeq;

        return $this;
    }
}
