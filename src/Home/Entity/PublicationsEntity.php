<?php

namespace App\Home\Entity;

use Doctrine\ORM\Mapping as ORM;

/*
The table references is a public table that contains the entries for
the metadata to be used in the Training Page. So, the Training Page
has a list of several references that cite the EFI Tools. Since it is
a growing list, the current website stores the list in a database with
the schema described below. Here, we create an Entity that will work
to get information from the References table or set information in that
same table.
*/

#[ORM\Entity]
#[ORM\Table(name: "publications")]
// The class References will be referenced when we set or get information from this table
class PublicationsEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private int $id;
    // Publication date
    #[ORM\Column(type: "string", length: 255, nullable: true)]
    private ?string $date;
    // Publication title
    #[ORM\Column(type: "string", length: 255)]
    private string $title;
    // Publication author
    #[ORM\Column(type: "text", nullable: true)]
    private ?string $author;
    // Publication year
    #[ORM\Column(type: "string", length: 4, nullable: true)]
    private ?string $year;
    // Pages (check if this is an interval like 10-15)
    #[ORM\Column(type: "string", length: 50, nullable: true)]
    private ?string $pages;
    // Volume of the issue
    #[ORM\Column(type: "string", length: 50, nullable: true)]
    private ?string $volume;
    // Number of the issue
    #[ORM\Column(type: "string", length: 50, nullable: true)]
    private ?string $number;
    // Subtitle
    #[ORM\Column(type: "string", length: 255, nullable: true)]
    private ?string $secondaryTitle;
    // Edition
    #[ORM\Column(type: "string", length: 100, nullable: true)]
    private ?string $edition;
    // Publisher of the Journal
    #[ORM\Column(type: "string", length: 100, nullable: true)]
    private ?string $publisher;
    // Place where the Journal was Published
    #[ORM\Column(type: "string", length: 100, nullable: true)]
    private ?string $placePublished;
    // URL for the Publication
    #[ORM\Column(type: "string", length: 255, nullable: true)]
    private ?string $url;
    // DOI or other identifier
    #[ORM\Column(type: "string", length: 255, nullable: true)]
    private ?string $electronicResourceNumber;
    
    public function getId(): int { return $this->id; }
    public function getDate(): ?string { return $this->date; }
    public function getTitle(): string { return $this->title; }
    public function getAuthor(): ?string { return $this->author; }
    public function getYear(): ?string { return $this->year; }
    public function getPages(): ?string { return $this->pages; }
    public function getVolume(): ?string { return $this->volume; }
    public function getNumber(): ?string { return $this->number; }
    public function getSecondaryTitle(): ?string { return $this->secondaryTitle; }
    public function getEdition(): ?string { return $this->edition; }
    public function getPublisher(): ?string { return $this->publisher; }
    public function getPlacePublished(): ?string { return $this->placePublished; }
    public function getUrl(): ?string { return $this->url; }
    public function getElectronicResourceNumber(): ?string { return $this->electronicResourceNumber; }

    public function setDate(?string $date): self { $this->date = $date; return $this; }
    public function setTitle(string $title): self { $this->title = $title; return $this; }
    public function setAuthor(?string $author): self { $this->author = $author; return $this; }
    public function setYear(?string $year): self { $this->year = $year; return $this; }
    public function setPages(?string $pages): self { $this->pages = $pages; return $this; }
    public function setVolume(?string $volume): self { $this->volume = $volume; return $this; }
    public function setNumber(?string $number): self { $this->number = $number; return $this; }
    public function setSecondaryTitle(?string $secondaryTitle): self { $this->secondaryTitle = $secondaryTitle; return $this; }
    public function setEdition(?string $edition): self { $this->edition = $edition; return $this; }
    public function setPublisher(?string $publisher): self { $this->publisher = $publisher; return $this; }
    public function setPlacePublished(?string $placePublished): self { $this->placePublished = $placePublished; return $this; }
    public function setUrl(?string $url): self { $this->url = $url; return $this; }
    public function setElectronicResourceNumber(?string $electronicResourceNumber): self { $this->electronicResourceNumber = $electronicResourceNumber; return $this; }

}