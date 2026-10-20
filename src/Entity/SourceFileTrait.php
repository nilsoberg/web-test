<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

trait SourceFileTrait
{
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $sourceFileReference = null;

    public function getSourceFileReference(): ?string
    {
        return $this->sourceFileReference;
    }

    public function setSourceFileReference(?string $sourceFileReference): static
    {
        $this->sourceFileReference = $sourceFileReference;

		return $this;
    }
}
