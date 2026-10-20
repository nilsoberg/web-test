<?php

namespace App\Entity;

use App\Enum\SummaryJobType;
use Doctrine\ORM\Mapping as ORM;
use Vich\UploaderBundle\Mapping\Attribute as Vich;

#[ORM\Entity]
#[Vich\Uploadable()]
#[DisplayName('Convergence Ratio')]
class ESTConvergenceRatioJob extends Job
{
    use FilenameTrait, SequenceDatabaseTrait, SourceFileTrait;

    protected SummaryJobType $summaryJobType = SummaryJobType::EST;
}
