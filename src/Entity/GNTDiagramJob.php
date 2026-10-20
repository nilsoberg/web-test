<?php

namespace App\Entity;

use App\Enum\SummaryJobType;
use Doctrine\ORM\Mapping as ORM;

abstract class GNTDiagramJob extends Job
{
    protected SummaryJobType $summaryJobType = SummaryJobType::GND;
}
