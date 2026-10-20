<?php

namespace App\Entity;

use App\Auth\Entity\UserEntity;
use App\Enum\SummaryJobType;
use App\Enum\ThresholdMetric;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\HasLifecycleCallbacks]
#[DisplayName('SSN Finalization')]
class ESTSSNFinalizationJob extends Job
{
    use ExcludeFragmentsTrait, FilterByTaxonomyTrait, ResultStatsTrait, SequenceLengthTrait;

    #[ORM\Column(nullable: true)]
    private ?bool $computeNeighborhoodConnectivity = false;

    #[ORM\Column(nullable: true)]
    private ?bool $makeRepnodeNetworks = true;

    #[ORM\Column(nullable: true)]
    private ?int $thresholdValue = null;

    #[ORM\Column(nullable: true)]
    private ?int $maxSsnEdges = null;

    #[ORM\Column(length: 20, nullable: false, enumType: ThresholdMetric::class, options: ["default" => "alignment_score"])]
    private ThresholdMetric $thresholdMetric = ThresholdMetric::AlignmentScore;

    protected SummaryJobType $summaryJobType = SummaryJobType::EST;


    public function isComputeNeighborhoodConnectivity(): ?bool
    {
        return $this->computeNeighborhoodConnectivity;
    }

    public function setComputeNeighborhoodConnectivity(?bool $computeNeighborhoodConnectivity): static
    {
        $this->computeNeighborhoodConnectivity = $computeNeighborhoodConnectivity;

        return $this;
    }

    public function isMakeRepnodeNetworks(): ?bool
    {
        return $this->makeRepnodeNetworks;
    }

    public function setMakeRepnodeNetworks(?bool $makeRepnodeNetworks): static
    {
        $this->makeRepnodeNetworks = $makeRepnodeNetworks;

        return $this;
    }

    public function getMaxSsnEdges(): ?int
    {
        return $this->maxSsnEdges;
    }

    public function setMaxSsnEdges(?int $maxSsnEdges): static
    {
        $this->maxSsnEdges = $maxSsnEdges;

        return $this;
    }

    public function getThresholdValue(): ?int
    {
        return $this->thresholdValue;
    }

    public function setThresholdValue(?int $thresholdValue): static
    {
        $this->thresholdValue = $thresholdValue;

        return $this;
    }

    public function getThresholdMetric(): ThresholdMetric
    {
        return $this->thresholdMetric;
    }

    public function setThresholdMetric(ThresholdMetric $thresholdMetric): static
    {
        $this->thresholdMetric = $thresholdMetric;

        return $this;
    }
}
