<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Vich\UploaderBundle\Mapping\Attribute as Vich;

#[ORM\Entity]
#[Vich\Uploadable]
#[DisplayName('FASTA Header ID Lookup')]
class GNTDiagramFastaJob extends GNTDiagramJob
{
    use FilenameTrait, NeighborhoodSizeTrait;
}
