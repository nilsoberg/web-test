<?php

namespace App\Entity;

use App\Enum\DatabaseMetadataType;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
class MetagenomeMetadata extends DatabaseMetadata
{
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $metagenomes = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $metadata = null;


    public function getDatabaseType(): DatabaseMetadataType
    {
        return DatabaseMetadataType::Metagenome;
    }

    public function getMetagenomes(): ?array
    {
        return $this->metagenomes;
    }

    public function setMetagenomes(?array $metagenomes): static
    {
        $this->metagenomes = $metagenomes;

        return $this;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function setMetadata(?array $metadata): static
    {
        $this->metadata = $metadata;

        return $this;
    }

    public function __toString(): string
    {
        return sprintf('%s (%s)', $this->getPublicName(), $this->getDbName());
    }
}
