<?php

namespace App\Entity;

use App\Config\Constants;
use App\Enum\SummaryJobType;
use Doctrine\ORM\Mapping as ORM;

trait NeighborhoodSizeTrait
{
    #[ORM\Column(name: 'neighborhood_size', nullable: true)]
    private ?int $neighborhoodSize = Constants::DEFAULT_GNN_WINDOW;

    public function getNeighborhoodSize(): ?int
    {
        return $this->neighborhoodSize;
    }

    public function setNeighborhoodSize(?int $neighborhoodSize): static
    {
        $this->neighborhoodSize = $neighborhoodSize;

        return $this;
    }
}
