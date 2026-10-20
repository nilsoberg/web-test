<?php 
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Vich\UploaderBundle\Mapping\Attribute as Vich;

#[ORM\Entity]
#[Vich\Uploadable]
#[DisplayName('FASTA')]
class TaxonomyFastaJob extends TaxonomyJob
{
    use ExcludeFragmentsTrait, FastaJobResultsTrait, FilenameTrait, FilterByFamiliesTrait, FilterByTaxonomyTrait, SequenceImportTrait, UserUploadedIdsTrait;
}
