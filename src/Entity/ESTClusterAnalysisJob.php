<?php

namespace App\Entity;

use App\Enum\SummaryJobType;
use Doctrine\ORM\Mapping as ORM;
use Vich\UploaderBundle\Mapping\Attribute as Vich;

#[ORM\Entity]
#[Vich\Uploadable()]
#[DisplayName('Cluster Analysis')]
class ESTClusterAnalysisJob extends Job
{
    use ColorSSNTrait, FilenameTrait, ResultStatsTrait, SourceFileTrait;

    #[ORM\Column(nullable: true)]
    private ?int $maxNumSequences = null;

    #[ORM\Column(nullable: true)]
    private ?int $minNumSequences = null;

    #[ORM\Column(nullable: true)]
    private ?bool $makeWeblogo = true;

    #[ORM\Column(nullable: true)]
    private ?bool $computeConsensusResidues = true;

    #[ORM\Column(nullable: true)]
    private ?string $consensusResidues = "C";

    #[ORM\Column(nullable: true)]
    private ?array $pidThresholds = null;

    #[ORM\Column(nullable: true)]
    private ?bool $makeHmms = true;

    #[ORM\Column(nullable: true)]
    private ?bool $makeLengthHistograms = true;

    protected SummaryJobType $summaryJobType = SummaryJobType::EST;


    public function getMaxNumSequences(): ?int
    {
        return $this->maxNumSequences;
    }

    public function setMaxNumSequences(?int $maxNumSequences): static
    {
        $this->maxNumSequences = $maxNumSequences;

        return $this;
    }

    public function getMinNumSequences(): ?int
    {
        return $this->minNumSequences;
    }

    public function setMinNumSequences(?int $minNumSequences): static
    {
        $this->minNumSequences = $minNumSequences;

        return $this;
    }

    public function getMakeWeblogo(): ?bool
    {
        return $this->makeWeblogo;
    }

    public function setMakeWeblogo(?bool $makeWeblogo): static
    {
        $this->makeWeblogo = $makeWeblogo;

        return $this;
    }

    public function getComputeConsensusResidues(): ?bool
    {
        return $this->computeConsensusResidues;
    }

    public function setComputeConsensusResidues(?bool $computeConsensusResidues): static
    {
        $this->computeConsensusResidues = $computeConsensusResidues;

        return $this;
    }

    public function getConsensusResidues(): ?string
    {
        return $this->consensusResidues;
    }

    public function setConsensusResidues(?string $consensusResidues): static
    {
        $this->consensusResidues = $consensusResidues !== null ? mb_strtoupper($consensusResidues) : null;

        return $this;
    }

    public function getPidThresholds(): ?array
    {
        return $this->pidThresholds;
    }

    public function setPidThresholds(?array $pidThresholds): static
    {
        $this->pidThresholds = $pidThresholds;

        return $this;
    }

    public function getMakeHmms(): ?bool
    {
        return $this->makeHmms;
    }

    public function setMakeHmms(?bool $makeHmms): static
    {
        $this->makeHmms = $makeHmms;

        return $this;
    }

    public function getMakeLengthHistograms(): ?bool
    {
        return $this->makeLengthHistograms;
    }

    public function setMakeLengthHistograms(?bool $makeLengthHistograms): static
    {
        $this->makeLengthHistograms = $makeLengthHistograms;

        return $this;
    }
}
