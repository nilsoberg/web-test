<?php

namespace App\Entity;

use App\Enum\SummaryJobType;
use Doctrine\ORM\Mapping as ORM;
use Vich\UploaderBundle\Mapping\Attribute as Vich;

#[ORM\Entity]
#[Vich\Uploadable]
#[DisplayName('GND Viewer')]
class GNTViewDiagramJob extends Job
{
    use FilenameTrait;

    protected SummaryJobType $summaryJobType = SummaryJobType::GND;
}
