<?php

namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;

// Output values
trait UserUploadedIdsTrait
{
    // Results
    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $numMatchedIds = null;

    // Results
    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $numUnmatchedIds = null;


    public function getNumMatchedIds(): ?string
    {
        return $this->numMatchedIds;
    }

    public function setNumMatchedIds(?string $numMatchedIds): static
    {
        $this->numMatchedIds = $numMatchedIds;

        return $this;
    }

    public function getNumUnmatchedIds(): ?string
    {
        return $this->numUnmatchedIds;
    }

    public function setNumUnmatchedIds(?string $numUnmatchedIds): static
    {
        $this->numUnmatchedIds = $numUnmatchedIds;

        return $this;
    }


    public function getFormattedNumUploadedIds(): string
    {
        if (isset($this->numMatchedIds) && isset($this->numUnmatchedIds))
        {
            return $this->numMatchedIds + $this->numUnmatchedIds;
        }
        else
        {
            return 0;
        }
    }
}
