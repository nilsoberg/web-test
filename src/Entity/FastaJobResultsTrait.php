<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

trait FastaJobResultsTrait
{
    // Results
    #[ORM\Column(nullable: true)]
    private ?int $numFastaHeaders = null;


    public function getNumFastaHeaders(): ?int
    {
        return $this->numFastaHeaders;
    }

    public function setNumFastaHeaders(?int $numFastaHeaders): static
    {
        $this->numFastaHeaders = $numFastaHeaders;

        return $this;
    }
}
