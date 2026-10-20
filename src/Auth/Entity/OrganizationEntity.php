<?php

namespace App\Auth\Entity;

use App\Auth\Repository\OrganizationRepository;
use App\Entity\DatabaseMetadata;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OrganizationRepository::class)]
#[ORM\Table(name: 'organizations')]
class OrganizationEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $name;

    #[ORM\Column(type: 'boolean')]
    private bool $isDeleted = false;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isProtected = false;

    /**
     * @var Collection<int, UserEntity>
     */
    #[ORM\OneToMany(mappedBy: 'primaryOrganization', targetEntity: UserEntity::class)]
    private Collection $users;

    #[ORM\ManyToOne(targetEntity: DatabaseMetadata::class)]
    #[ORM\JoinColumn(name: "default_database_id", referencedColumnName: "id", nullable: true, onDelete: "SET NULL")]
    private ?DatabaseMetadata $defaultDatabase = null;


    public function __construct()
    {
        $this->users = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function isDeleted(): bool
    {
        return $this->isDeleted;
    }

    public function setIsDeleted(bool $isDeleted): static
    {
        $this->isDeleted = $isDeleted;

        return $this;
    }

    public function isProtected(): bool
    {
        return $this->isProtected;
    }

    public function setIsProtected(bool $isProtected): static
    {
        $this->isProtected = $isProtected;

        return $this;
    }

    /**
     * @return Collection<int, UserEntity>
     */
    public function getUsers(): Collection
    {
        return $this->users;
    }

    public function addUser(UserEntity $user): static
    {
        if (!$this->users->contains($user)) {
            $this->users->add($user);
            $user->setPrimaryOrganization($this);
        }
        return $this;
    }

    public function getDefaultDatabase(): ?DatabaseMetadata
    {
        return $this->defaultDatabase;
    }

    public function setDefaultDatabase(?DatabaseMetadata $defaultDatabase): static
    {
        $this->defaultDatabase = $defaultDatabase;

        return $this;
    }
}
