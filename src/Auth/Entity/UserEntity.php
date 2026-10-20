<?php

namespace App\Auth\Entity;

use App\Auth\Repository\UserRepository;
use App\Auth\Roles;
use App\Entity\Job;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
#[UniqueEntity(fields: ['email'], message: 'There is already an account with this email')]
class UserEntity implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $fullName;

    #[ORM\Column(type: 'string', length: 255, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Email]
    private string $email;

    #[ORM\Column(type: 'string', length: 255)]
    private string $password;

    #[ORM\Column(type: 'string', length: 64, nullable: true)]
    private ?string $timezone = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isDeleted = false;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $mustChangePassword = false;

    #[ORM\Column(type: 'boolean')]
    private bool $devFeaturesEnabled = false;

    // This column is only relevant when operating in "public" mode
    #[ORM\Column(type: 'boolean')]
    private bool $isPublicGhost = false;

    // This column is only relevant when operating in "public" mode
    #[ORM\Column(type: 'boolean')]
    private bool $isPublicVerified = false;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $sendJobStatusEmail = true;

    /**
     * The user's primary ("home") organization.
     * This is NULL only for Superadmins.
     */
    #[ORM\ManyToOne(targetEntity: OrganizationEntity::class)]
    #[ORM\JoinColumn(name: 'primary_organization_id', referencedColumnName: 'id', nullable: true)]
    private ?OrganizationEntity $primaryOrganization = null;

    #[ORM\ManyToOne(targetEntity: Role::class, inversedBy: 'usersWithGlobalRole')]
    #[ORM\JoinColumn(name: 'global_role_id', referencedColumnName: 'id', nullable: false)]
    private ?Role $globalRole = null;

    /**
     * @var Collection<int, Job>
     */
    #[ORM\OneToMany(targetEntity: Job::class, mappedBy: 'user')]
    private Collection $jobs;


    public function __construct()
    {
        $this->jobs = new ArrayCollection();
    }


    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFullName(): string
    {
        return $this->fullName;
    }

    public function setFullName(string $fullName): static
    {
        $this->fullName = $fullName;
        
        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = mb_strtolower($email);
        
        return $this;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;
        
        return $this;
    }

    public function getTimezone(): ?string
    {
        return $this->timezone;
    }

    public function setTimezone(?string $timezone): static
    {
        $this->timezone = $timezone;

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

    public function getMustChangePassword(): bool
    {
        return $this->mustChangePassword;
    }

    public function setMustChangePassword(bool $mustChangePassword): static
    {
        $this->mustChangePassword = $mustChangePassword;

        return $this;
    }

    public function isDevFeaturesEnabled(): bool
    {
        return $this->devFeaturesEnabled;
    }

    public function setDevFeaturesEnabled(bool $devFeaturesEnabled): static
    {
        $this->devFeaturesEnabled = $devFeaturesEnabled;

        return $this;
    }

    public function isPublicGhost(): bool
    {
        return $this->isPublicGhost;
    }

    public function setIsPublicGhost(bool $isPublicGhost): static
    {
        $this->isPublicGhost = $isPublicGhost;

        return $this;
    }

    public function isPublicVerified(): bool
    {
        return $this->isPublicVerified;
    }

    public function setIsPublicVerified(bool $isPublicVerified): self
    {
        $this->isPublicVerified = $isPublicVerified;

        return $this;
    }

    public function isSendJobStatusEmail(): bool
    {
        return $this->sendJobStatusEmail;
    }

    public function setSendJobStatusEmail(bool $sendJobStatusEmail): self
    {
        $this->sendJobStatusEmail = $sendJobStatusEmail;

        return $this;
    }

    public function getPrimaryOrganization(): ?OrganizationEntity
    {
        return $this->primaryOrganization;
    }

    public function setPrimaryOrganization(?OrganizationEntity $primaryOrganization): static
    {
        $this->primaryOrganization = $primaryOrganization;

        return $this;
    }

    public function getGlobalRole(): ?Role
    {
        return $this->globalRole;
    }

    public function setGlobalRole(?Role $role): static
    {
        $this->globalRole = $role;

        return $this;
    }

    /**
     * @return Collection<int, Job>
     */
    public function getJobs(): Collection
    {
        return $this->jobs;
    }

    public function addJob(Job $job): static
    {
        if (!$this->jobs->contains($job)) {
            $this->jobs->add($job);
            $job->setUser($this);
        }

        return $this;
    }

    public function removeJob(Job $job): static
    {
        if ($this->jobs->removeElement($job)) {
            // set the owning side to null (unless already changed)
            if ($job->getUser() === $this) {
                $job->setUser(null);
            }
        }

        return $this;
    }


    public function getUsername(): string
    {
        return $this->email;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function getInitials(): string
    {
        if (!$this->fullName)
        {
            return 'U';
        }
        $words = explode(' ', $this->fullName);
        $initials = '';
        foreach ($words as $word)
        {
            $initials .= strtoupper($word[0]);
        }
        return $initials;
    }

	public function getRoles(): array
    {
        $roles = [];
        if ($this->globalRole) {
            $roles[] = $this->globalRole->getName();
        }

        // Guarantee every authenticated user gets baseline role
        $roles[] = Roles::ROLE_USER;

        return array_unique($roles);
	}

    public function eraseCredentials(): void
    {
    }
}
