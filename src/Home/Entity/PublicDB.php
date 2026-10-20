<?php

namespace App\Home\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: "public_databases")]
class PublicDB
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    #[ORM\Column(type: "datetime")]
    private \DateTimeInterface $date;

    #[ORM\Column(type: "string", length: 255)]
    private string $uniprot;

    #[ORM\Column(type: "string", length: 255)]
    private string $interpro;

    #[ORM\Column(type: "string", length: 255)]
    private string $ena;

    #[ORM\Column(type: "boolean")]
    private bool $active = false;

    public function __construct() { $this->date = new \DateTimeImmutable(); }
    public function getId(): ?int { return $this->id; }
    public function getDate(): \DateTimeInterface { return $this->date; }
    public function getUniprot(): string { return $this->uniprot; }
    public function getInterpro(): string { return $this->interpro; }
    public function getEna(): string { return $this->ena; }
    public function isActive(): bool { return $this->active; }

    public function setDate(\DateTimeInterface $date): self { $this->date = $date; return $this; }
    public function setUniprot(string $uniprot): self { $this->uniprot = $uniprot; return $this; }
    public function setInterpro(string $interpro): self { $this->interpro = $interpro; return $this; }
    public function setEna(string $ena): self { $this->ena = $ena; return $this; }
    public function setActive(bool $active): self { $this->active = $active; return $this; }

}