<?php

namespace App\Entity;

use App\Auth\Entity\UserEntity;
use App\Enum\AllByAllSearchSensitivity;
use App\Enum\AllByAllSearchType;
use App\Enum\SummaryJobType;
use Doctrine\ORM\Mapping as ORM;
use Vich\UploaderBundle\Mapping\Attribute as Vich;

#[ORM\Entity]
#[Vich\Uploadable]
abstract class ESTGenerateJob extends Job implements FamilyJobInterface
{
    use SequenceImportTrait;

    protected SummaryJobType $summaryJobType = SummaryJobType::EST;

    #[ORM\Column(nullable: true)]
    private ?int $allByAllBlastEValue = \App\Config\Constants::DEFAULT_ALL_BY_ALL_BLAST_EVALUE;

    #[ORM\Column(enumType: AllByAllSearchType::class)]
    private ?AllByAllSearchType $axaSearchType = AllByAllSearchType::Blast;

    #[ORM\Column(enumType: AllByAllSearchSensitivity::class)]
    private ?AllByAllSearchSensitivity $axaSearchSens = AllByAllSearchSensitivity::Normal;

    public function getAllByAllBlastEValue(): ?int
    {
        return $this->allByAllBlastEValue;
    }

    public function setAllByAllBlastEValue(?int $allByAllBlastEValue): static
    {
        $this->allByAllBlastEValue = $allByAllBlastEValue;

        return $this;
    }

    public function getAxaSearchType(): ?AllByAllSearchType
    {
        return $this->axaSearchType;
    }

    public function setAxaSearchType(?AllByAllSearchType $axaSearchType): static
    {
        $this->axaSearchType = $axaSearchType;

        return $this;
    }

    public function getAxaSearchSens(): ?AllByAllSearchSensitivity 
    {
        return $this->axaSearchSens;
    }

    public function setAxaSearchSens(?AllByAllSearchSensitivity $axaSearchSens): static
    {
        $this->axaSearchSens = $axaSearchSens;

        return $this;
    }
}
