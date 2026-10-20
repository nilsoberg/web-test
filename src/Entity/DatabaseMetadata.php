<?php

namespace App\Entity;

use App\Auth\Entity\OrganizationEntity;
use App\Enum\DatabaseMetadataType;
use App\Repository\DatabaseMetadataRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: DatabaseMetadataRepository::class)]
#[ORM\InheritanceType('SINGLE_TABLE')]
#[ORM\DiscriminatorColumn(name: 'db_type', type: 'string')]
#[ORM\DiscriminatorMap([
    'est' => SequenceDatabaseMetadata::class,
    'metagenome' => MetagenomeMetadata::class,
])]
abstract class DatabaseMetadata
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $publicName = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'The internal database name cannot be blank.')]
    #[Assert\Regex(
        pattern: '/^[a-zA-Z0-9_]+$/',
        message: 'The internal database name can only contain letters, numbers, and underscores.'
    )]
    private ?string $dbName = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isActive = false;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isPublic = false;

    #[ORM\ManyToOne(targetEntity: OrganizationEntity::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?OrganizationEntity $organization = null;


    abstract public function getDatabaseType(): DatabaseMetadataType;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPublicName(): ?string
    {
        return $this->publicName;
    }

    public function setPublicName(string $publicName): static
    {
        $this->publicName = $publicName;

        return $this;
    }

    public function getDbName(): ?string
    {
        return $this->dbName;
    }

    public function setDbName(string $dbName): static
    {
        $this->dbName = $dbName;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getOrganization(): ?OrganizationEntity
    {
        return $this->organization;
    }

    public function setOrganization(?OrganizationEntity $organization): static
    {
        $this->organization = $organization;
        return $this;
    }

    public function isPublic(): bool
    {
        return $this->isPublic;
    }

    public function setIsPublic(bool $isPublic): static
    {
        $this->isPublic = $isPublic;

        return $this;
    }
}
