<?php

namespace App\Entity;

use App\Config\Constants;
use App\Enum\SequenceType;
use Doctrine\ORM\Mapping as ORM;

trait SequenceDatabaseTrait
{
    #[ORM\Column(nullable: true)]
    private ?int $blastEValue = Constants::DEFAULT_BLAST_EVALUE;

    #[ORM\Column(nullable: true)]
    private ?int $maxBlastSequences = null;


    public function getBlastEValue(): ?int
    {
        return $this->blastEValue;
    }

    public function setBlastEValue(?int $blastEValue): static
    {
        $this->blastEValue = $blastEValue;

        return $this;
    }

    public function getMaxBlastSequences(): ?int
    {
        return $this->maxBlastSequences;
    }

    public function setMaxBlastSequences(?int $maxBlastSequences): static
    {
        $this->maxBlastSequences = $maxBlastSequences;

        return $this;
    }
}
