<?php

namespace App\Entity;

use App\Enum\SummaryJobType;
use Doctrine\ORM\Mapping as ORM;
use Vich\UploaderBundle\Mapping\Attribute as Vich;

#[ORM\Entity]
#[Vich\Uploadable()]
#[DisplayName('Color SSN')]
class ESTColorSSNJob extends Job
{
    use ColorSSNTrait, FilenameTrait, ResultStatsTrait, SourceFileTrait;

    protected SummaryJobType $summaryJobType = SummaryJobType::EST;
}
