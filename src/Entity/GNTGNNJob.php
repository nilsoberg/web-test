<?php

namespace App\Entity;

use App\Enum\SummaryJobType;
use Doctrine\ORM\Mapping as ORM;
use Vich\UploaderBundle\Mapping\Attribute as Vich;

#[ORM\Entity]
#[Vich\Uploadable()]
#[DisplayName('GNT')]
class GNTGNNJob extends Job
{
    use ColorSSNTrait, FilenameTrait, ResultStatsTrait, NeighborhoodSizeTrait, SourceFileTrait;

    #[ORM\Column(nullable: true)]
    private ?float $cooccurrence = \App\Config\Constants::DEFAULT_GNN_COOCCURRENCE;

    protected SummaryJobType $summaryJobType = SummaryJobType::GNT;


    public function getCooccurrence(): ?float
    {
        return $this->cooccurrence;
    }

    public function setCooccurrence(?float $cooccurrence): static
    {
        $this->cooccurrence = $cooccurrence;

        return $this;
    }
}
