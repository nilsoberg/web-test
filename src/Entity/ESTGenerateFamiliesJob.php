<?php

namespace App\Entity;

use App\Auth\Entity\UserEntity;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[DisplayName('Families')]
class ESTGenerateFamiliesJob extends ESTGenerateJob
{
    use DomainBoundariesTrait, ExcludeFragmentsTrait, FilterByTaxonomyTrait, ProteinFamilyAdditionTrait, UnirefVersionTrait;
}
