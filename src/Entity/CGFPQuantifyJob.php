<?php

namespace App\Entity;

use App\Entity\DatabaseMetadata;
use App\Enum\CGFPSearchType;
use App\Enum\SummaryJobType;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[DisplayName('CGFP Quantify')]
class CGFPQuantifyJob extends Job
{
    use CGFPResultsTrait, ColorSSNTrait;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $metagenomes = null;

    #[ORM\Column(enumType: CGFPSearchType::class)]
    private ?CGFPSearchType $quantifySearchType = CGFPSearchType::USEARCH;

    #[ORM\Column(name: 'metagenome_db_version', length: 255, nullable: true)]
    private ?string $metagenomeDbVersion = null;

    #[ORM\ManyToOne(targetEntity: DatabaseMetadata::class)]
    #[ORM\JoinColumn(name: 'metagenome_db_id', nullable: true)]
    private ?DatabaseMetadata $metagenomeDatabase = null; // Used by Symfony Form

    // Results
    #[ORM\Column(nullable: true)]
    private ?int $numConsensusSeqWithHits = null;

    protected SummaryJobType $summaryJobType = SummaryJobType::CGFP;


    public function getMetagenomes(): ?string
    {
        return $this->metagenomes;
    }

    public function setMetagenomes(?string $metagenomes): static
    {
        $this->metagenomes = $metagenomes;

        return $this;
    }

    public function getQuantifySearchType(): ?CGFPSearchType
    {
        return $this->quantifySearchType;
    }

    public function setQuantifySearchType(?CGFPSearchType $quantifySearchType): static
    {
        $this->quantifySearchType = $quantifySearchType;

        return $this;
    }

    public function getNumConsensusSeqWithHits(): ?int
    {
        return $this->numConsensusSeqWithHits;
    }

    public function setNumConsensusSeqWithHits(?int $numConsensusSeqWithHits): static
    {
        $this->numConsensusSeqWithHits = $numConsensusSeqWithHits;

        return $this;
    }

    public function getMetagenomeDbVersion(): ?string
    {
        return $this->metagenomeDbVersion;
    }

    public function setMetagenomeDbVersion(?string $metagenomeDbVersion): static
    {
        $this->metagenomeDbVersion = $metagenomeDbVersion;

        return $this;
    }

    public function getMetagenomeDatabase(): ?DatabaseMetadata
    {
        return $this->metagenomeDatabase;
    }

    public function setMetagenomeDatabase(?DatabaseMetadata $metagenomeDatabase): static
    {
        $this->metagenomeDatabase = $metagenomeDatabase;

        return $this;
    }
}
