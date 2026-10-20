<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Vich\UploaderBundle\Mapping\Attribute as Vich;

#[ORM\Entity]
#[DisplayName('Sequence BLAST')]
#[Vich\Uploadable]
class GNTDiagramBlastJob extends GNTDiagramJob
{
    use ExcludeFragmentsTrait, FilenameTrait, NeighborhoodSizeTrait, SequenceDatabaseTrait, UnirefVersionTrait;
}
