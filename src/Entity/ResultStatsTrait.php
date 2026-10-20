<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

trait ResultStatsTrait
{
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $resultFileStats = null;

    public function getResultFileStats(): ?string
    {
        return $this->resultFileStats;
    }

    public function setResultFileStats(?string $resultFileStats): static
    {
        $this->resultFileStats = $resultFileStats;

        return $this;
    }
}
