<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Vich\UploaderBundle\Mapping\Attribute as Vich;

#[ORM\Entity]
#[Vich\Uploadable]
#[DisplayName('FASTA')]
class ESTGenerateFastaJob extends ESTGenerateJob
{
    use FastaJobResultsTrait, FilenameTrait, FilterByTaxonomyTrait, ProteinFamilyAdditionTrait, UnirefVersionTrait, UserUploadedIdsTrait;
}
