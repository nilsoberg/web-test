<?php

namespace App\Entity;

use App\Config\Constants;
use Doctrine\ORM\Mapping as ORM;

trait SequenceLengthTrait
{
    #[ORM\Column(nullable: true)]
    private ?int $minLength = null;

    #[ORM\Column(nullable: true)]
    private ?int $maxLength = null;

    public function getMinLength(): ?int
    {
        return $this->minLength;
    }

    public function setMinLength(?int $minLength): static
    {
        $this->minLength = $minLength;

        return $this;
    }

    public function getMaxLength(): ?int
    {
        return $this->maxLength;
    }

    public function setMaxLength(?int $maxLength): static
    {
        $this->maxLength = $maxLength;

        return $this;
    }

    #[ORM\PrePersist]
    public function setDefaultsOnNull(): void
    {
        if ($this->minLength === null) {
            $this->minLength = Constants::DEFAULT_MIN_LENGTH;
        }

        if ($this->maxLength === null) {
            $this->maxLength = Constants::DEFAULT_MAX_LENGTH;
        }
    }
}
