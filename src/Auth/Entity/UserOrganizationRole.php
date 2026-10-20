<?php

namespace App\Auth\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity]
#[ORM\Table(name: 'user_organization_role')]
#[UniqueEntity(
    fields: ['user', 'organization'],
    message: 'This user is already associated with this organization.',
    errorPath: 'organization' // Puts the error on the 'organization' field in the form
)]
class UserOrganizationRole
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: UserEntity::class, inversedBy: 'organizationRoles')]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    private UserEntity $user;

    #[ORM\ManyToOne(targetEntity: OrganizationEntity::class, inversedBy: 'userRoles')]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    private OrganizationEntity $organization;

    #[ORM\ManyToOne(targetEntity: Role::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Role $role;


    public function __construct()
    {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): UserEntity
    {
        return $this->user;
    }

    public function setUser(UserEntity $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getOrganization(): OrganizationEntity
    {
        return $this->organization;
    }

    public function setOrganization(OrganizationEntity $organization): self
    {
        $this->organization = $organization;

        return $this;
    }

    public function getRole(): Role
    {
        return $this->role;
    }

    public function setRole(Role $role): self
    {
        $this->role = $role;

        return $this;
    }
}
