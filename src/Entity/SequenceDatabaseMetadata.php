<?php

namespace App\Entity;

use App\Enum\DatabaseMetadataType;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
class SequenceDatabaseMetadata extends DatabaseMetadata
{
    public function getDatabaseType(): DatabaseMetadataType
    {
        return DatabaseMetadataType::EST;
    }
}
