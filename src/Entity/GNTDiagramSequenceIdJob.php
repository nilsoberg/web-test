<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Vich\UploaderBundle\Mapping\Attribute as Vich;

#[ORM\Entity]
#[Vich\Uploadable]
#[DisplayName('Sequence ID Lookup')]
class GNTDiagramSequenceIdJob extends GNTDiagramJob
{
    use ExcludeFragmentsTrait, FilenameTrait, NeighborhoodSizeTrait, SequenceDatabaseTrait, UnirefVersionTrait;
}
