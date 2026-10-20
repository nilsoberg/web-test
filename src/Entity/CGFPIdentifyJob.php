<?php

namespace App\Entity;

use App\Enum\AllByAllSearchType;
use App\Enum\SummaryJobType;
use Doctrine\ORM\Mapping as ORM;
use Vich\UploaderBundle\Mapping\Attribute as Vich;

#[ORM\Entity]
#[Vich\Uploadable()]
#[ORM\HasLifecycleCallbacks]
#[DisplayName('CGFP Identify')]
class CGFPIdentifyJob extends Job
{
    use CGFPResultsTrait, ColorSSNTrait, FilenameTrait, SequenceLengthTrait, SourceFileTrait, UnirefVersionTrait;

    #[ORM\Column(nullable: true)]
    private ?int $cdhitSequenceIdentity = null;

    #[ORM\Column(enumType: AllByAllSearchType::class)]
    private ?AllByAllSearchType $searchType = AllByAllSearchType::Diamond;


    protected SummaryJobType $summaryJobType = SummaryJobType::CGFP;


    public function getCdhitSequenceIdentity(): ?int
    {
        return $this->cdhitSequenceIdentity;
    }

    public function setCdhitSequenceIdentity(?int $cdhitSequenceIdentity): static
    {
        $this->cdhitSequenceIdentity = $cdhitSequenceIdentity;

        return $this;
    }

    public function getSearchType(): ?AllByAllSearchType
    {
        return $this->searchType;
    }

    public function setSearchType(?AllByAllSearchType $searchType): static
    {
        $this->searchType = $searchType;

        return $this;
    }
}
