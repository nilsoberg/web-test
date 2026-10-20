<?php

namespace App\Entity;

use App\Enum\SequenceType;
use Doctrine\ORM\Mapping as ORM;
use Vich\UploaderBundle\Mapping\Attribute as Vich;

#[ORM\Entity]
#[Vich\Uploadable]
#[DisplayName('Accession IDs')]
class TaxonomyAccessionJob extends TaxonomyJob
{
    use AccessionJobResultsTrait, ExcludeFragmentsTrait, FilenameTrait, FilterByFamiliesTrait, FilterByTaxonomyTrait, SequenceImportTrait, UnirefVersionTrait, UserUploadedIdsTrait;
}
