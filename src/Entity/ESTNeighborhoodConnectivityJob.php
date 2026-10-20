<?php

namespace App\Entity;

use App\Enum\SummaryJobType;
use Doctrine\ORM\Mapping as ORM;
use Vich\UploaderBundle\Mapping\Attribute as Vich;

#[ORM\Entity]
#[Vich\Uploadable()]
#[DisplayName('Neighborhood Connectivity')]
class ESTNeighborhoodConnectivityJob extends Job
{
    use FilenameTrait, ResultStatsTrait, SourceFileTrait;

    protected SummaryJobType $summaryJobType = SummaryJobType::EST;
}
