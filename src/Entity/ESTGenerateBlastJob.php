<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Vich\UploaderBundle\Mapping\Attribute as Vich;

#[ORM\Entity]
#[Vich\Uploadable]
#[DisplayName('BLAST')]
class ESTGenerateBlastJob extends ESTGenerateJob
{
    use ExcludeFragmentsTrait, FilenameTrait, FilterByTaxonomyTrait, ProteinFamilyAdditionTrait, SequenceDatabaseTrait, UnirefVersionTrait;

    // Results
    #[ORM\Column(nullable: true)]
    private ?int $numBlastUnmatchedIds = null;

    // Results
    #[ORM\Column(nullable: true)]
    private ?int $numBlastRetrievedIds = null;


    public function getNumBlastRetrievedIds(): ?int
    {
        return $this->numBlastRetrievedIds;
    }

    public function setNumBlastRetrievedIds(?int $numBlastRetrievedIds): static
    {
        $this->numBlastRetrievedIds = $numBlastRetrievedIds;

        return $this;
    }

    public function getNumBlastUnmatchedIds(): ?int
    {
        return $this->numBlastUnmatchedIds;
    }

    public function setNumBlastUnmatchedIds(?int $numBlastUnmatchedIds): static
    {
        $this->numBlastUnmatchedIds = $numBlastUnmatchedIds;

        return $this;
    }


    // Virtual property to help job summary service work
    public function getBlastQueryLink(): ?string
    {
        return 'link';
    }

    // Virtual property to help job summary service work
    public function getBlastQuery(): ?string
    {
        return 'query';
    }
}
