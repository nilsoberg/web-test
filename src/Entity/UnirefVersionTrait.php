<?php

namespace App\Entity;

use App\Enum\SequenceType;
use Doctrine\ORM\Mapping as ORM;

trait UnirefVersionTrait
{
    #[ORM\Column(name: 'sequence_version', enumType: SequenceType::class)]
    private ?SequenceType $unirefVersion = SequenceType::UniProt;


    public function getUnirefVersion(): ?SequenceType
    {
        return $this->unirefVersion;
    }

    public function setUnirefVersion(?SequenceType $unirefVersion): static
    {
        $this->unirefVersion = $unirefVersion;

        return $this;
    }

    /**
     * Virtual property used by the JobSummaryService.
     */
    public function getUseUniref(): bool
    {
        return
            $this->unirefVersion === SequenceType::UniRef90 ||
            $this->unirefVersion === SequenceType::UniRef50;
    }
}
