<?php

namespace App\Auth\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'org_databases')]
class OrgDatabase
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $orgId;

    #[ORM\Column(length: 255)]
    private string $filename;

    #[ORM\Column(length: 255)]
    private string $path;

    #[ORM\Column(length: 50)]
    private string $method;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $created;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $updated;

    #[ORM\Column(type: 'boolean')]
    private bool $shared = false;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $version = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $sharedWith = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $name = '';

    #[ORM\Column(type: 'string', length: 255)]
    private string $originalFilename;

    #[ORM\Column(type: 'string', length: 255)]
    private string $uniqueFilename;

    public function getId(): ?int { return $this->id; }
    public function getOrgId(): string { return $this->orgId; }
    public function getFilename(): string { return $this->filename; }
    public function getPath(): string { return $this->path; }
    public function getMethod(): string { return $this->method; }
    public function getCreated(): \DateTimeInterface { return $this->created; }
    public function getUpdated(): \DateTimeInterface { return $this->updated; }
    public function isShared(): bool { return $this->shared; }
    public function getVersion(): ?string { return $this->version; }
    public function getSharedWith(): ?string { return $this->sharedWith; }
    public function getName(): string { return $this->name; }
    public function getDateCreated(): \DateTimeInterface { return $this->getCreated(); }
    public function getOriginalFilename(): string { return $this->originalFilename; }
    public function getUniqueFilename(): string { return $this->uniqueFilename; }

    public function setOrgId(string $orgId): self { $this->orgId = $orgId; return $this; }
    public function setFilename(string $filename): self { $this->filename = $filename; return $this; }
    public function setPath(string $path): self { $this->path = $path; return $this; }
    public function setMethod(string $method): self { $this->method = $method; return $this; }
    public function setCreated(\DateTimeInterface $created): self { $this->created = $created; return $this; }
    public function setUpdated(\DateTimeInterface $updated): self { $this->updated = $updated; return $this; }
    public function setShared(bool $shared): self { $this->shared = $shared; return $this; }
    public function setVersion(?string $version): self { $this->version = $version; return $this; }
    public function setSharedWith(?string $sharedWith): self { $this->sharedWith = $sharedWith; return $this; }
    public function setName(string $name): self { $this->name = $name; return $this; }
    public function setOriginalFilename(string $originalFilename): self { $this->originalFilename = $originalFilename; return $this; }
    public function setUniqueFilename(string $uniqueFilename): self { $this->uniqueFilename = $uniqueFilename; return $this; }

}