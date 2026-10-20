<?php

namespace App\Entity;

use App\Enum\SummaryJobType;
use Doctrine\ORM\Mapping as ORM;

abstract class TaxonomyJob extends Job
{
    protected SummaryJobType $summaryJobType = SummaryJobType::TAXONOMY;
}
