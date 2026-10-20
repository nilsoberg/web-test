<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

trait ExcludeFragmentsTrait
{
    #[ORM\Column(nullable: true)]
    private ?bool $excludeFragments = false;

    // Results
    #[ORM\Column(nullable: true)]
    private ?int $numFragmentFiltered = null;


    public function isExcludeFragments(): ?bool
    {
        return $this->excludeFragments;
    }

    public function setExcludeFragments(?bool $excludeFragments): static
    {
        $this->excludeFragments = $excludeFragments;

        return $this;
    }

    public function getNumFragmentFiltered(): ?int
    {
        return $this->numFragmentFiltered;
    }

    public function setNumFragmentFiltered(?int $numFragmentFiltered): static
    {
        $this->numFragmentFiltered = $numFragmentFiltered;

        return $this;
    }
}
