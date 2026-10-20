<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Vich\UploaderBundle\Mapping\Attribute as Vich;

#[ORM\Entity]
#[Vich\Uploadable]
#[DisplayName('Accession')]
class ESTGenerateAccessionJob extends ESTGenerateJob
{
    use AccessionJobResultsTrait, DomainBoundariesTrait, ExcludeFragmentsTrait, FilenameTrait, FilterByFamiliesTrait, FilterByTaxonomyTrait, ProteinFamilyAdditionTrait, UnirefVersionTrait, UserUploadedIdsTrait;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $domainFamily = null;


    public function getDomainFamily(): ?string
    {
        return $this->domainFamily;
    }

    public function setDomainFamily(?string $domainFamily): static
    {
        $this->domainFamily = $domainFamily;

        return $this;
    }


    public function getFormattedDomainFamily(): string
    {
        if (!$this->domainFamily)
        {
            return "";
        }

        $domainOption = $this->getFormattedDomain();
        if (!$domainOption)
        {
            return "";
        }

        $domainOption .= " " . $this->domainFamily;

        return $domainOption;
    }
}
