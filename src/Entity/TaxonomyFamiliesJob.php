<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\HasLifecycleCallbacks]
#[DisplayName('Families')]
class TaxonomyFamiliesJob extends TaxonomyJob implements FamilyJobInterface
{
    use ExcludeFragmentsTrait, FilterByTaxonomyTrait, ProteinFamilyAdditionTrait, SequenceImportTrait, SequenceLengthTrait, UnirefVersionTrait;
}
