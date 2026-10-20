<?php

namespace App\Home\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: "patents")]
class PatentsEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private int $id;
    // Patent number
    #[ORM\Column(type: "string", length: 255, nullable: true)]
    private ?string $number;
    // Patent title
    #[ORM\Column(type: "string", length: 255)]
    private string $title;
    // Patent author
    #[ORM\Column(type: "text", nullable: true)]
    private ?string $author;
    // Patent year
    #[ORM\Column(type: "string", length: 4, nullable: true)]
    private ?string $year;
    // Patent month
    #[ORM\Column(type: "string", length: 4, nullable: true)]
    private ?string $month;
    // Patent day
    #[ORM\Column(type: "string", length: 4, nullable: true)]
    private ?string $day;

    // Functions to get information from the database or set when appropriate
    
    public function getId(): int { return $this->id; }
    public function getNumber(): ?string { return $this->number; }
    public function getTitle(): string { return $this->title; }
    public function getAuthor(): ?string { return $this->author; }
    public function getYear(): ?string { return $this->year; }
    public function getMonth(): ?string { return $this->month; }
    public function getDay(): ?string { return $this->day; }

    public function setNumber(?string $number): self { $this->number = $number; return $this; }
    public function setTitle(string $title): self { $this->title = $title; return $this; }
    public function setAuthor(?string $author): self { $this->author = $author; return $this; }
    public function setYear(?string $year): self { $this->year = $year; return $this; }
    public function setMonth(?string $month): self { $this->month = $month; return $this; }
    public function setDay(?string $day): self { $this->day = $day; return $this; }

}