<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

trait AccessionJobResultsTrait
{
    // Results
    #[ORM\Column(nullable: true)]
    private ?int $numForeignIds = null;


    public function getNumForeignIds(): ?int
    {
        return $this->numForeignIds;
    }

    public function setNumForeignIds(?int $numForeignIds): static
    {
        $this->numForeignIds = $numForeignIds;

        return $this;
    }
}
