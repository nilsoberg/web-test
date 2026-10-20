<?php

namespace App\Auth\Entity;

use App\Auth\Roles;
use App\Repository\RoleRepository;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Role
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private string $name;

    // --- Inverse side of UserEntity::globalRole (OneToMany) ---
    /**
     * @var Collection<int, UserEntity>
     */
    #[ORM\OneToMany(mappedBy: 'globalRole', targetEntity: UserEntity::class)]
    private Collection $usersWithGlobalRole;


    public function __construct()
    {
        $this->usersWithGlobalRole = new ArrayCollection();
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

    /**
     * @return Collection<int, UserEntity>
     */
    public function getUsersWithGlobalRole(): Collection
    {
        return $this->usersWithGlobalRole;
    }

    public function addUsersWithGlobalRole(UserEntity $user): static
    {
        if (!$this->usersWithGlobalRole->contains($user)) {
            $this->usersWithGlobalRole->add($user);
            // Optional: set the owning side here if you manage it manually,
            // but often the owning side (UserEntity) will handle its own assignment.
            // $user->setGlobalRole($this);
        }
        return $this;
    }

    public function removeUsersWithGlobalRole(UserEntity $user): static
    {
        $this->usersWithGlobalRole->removeElement($user);
        // Optional: remove the owning side here
        // if ($user->getGlobalRole() === $this) {
        //     $user->setGlobalRole(null);
        // }
        return $this;
    }

    public function getDisplayName(): string
    {
        return Roles::getLabel($this->name);
    }
}
