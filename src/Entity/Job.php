<?php

namespace App\Entity;

use App\Auth\Entity\UserEntity;
use App\Entity\DatabaseMetadata;
use App\Enum\JobStatus;
use App\Enum\SummaryJobType;
use App\Repository\JobRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: JobRepository::class)]
#[ORM\InheritanceType('SINGLE_TABLE')]
#[ORM\DiscriminatorColumn(name: 'job_type', type: 'string')]
#[ORM\DiscriminatorMap([
    'est_generate_blast' => ESTGenerateBlastJob::class,
    'est_generate_families' => ESTGenerateFamiliesJob::class,
    'est_generate_fasta' => ESTGenerateFastaJob::class,
    'est_generate_accession' => ESTGenerateAccessionJob::class,
    'est_color_ssn' => ESTColorSSNJob::class,
    'est_neighborhood_connectivity' => ESTNeighborhoodConnectivityJob::class,
    'est_cluster_analysis' => ESTClusterAnalysisJob::class,
    'est_convergence_ratio' => ESTConvergenceRatioJob::class,
    'est_ssn_finalization' => ESTSSNFinalizationJob::class,
    'gnt_gnn' => GNTGNNJob::class,
    'gnt_diagram_blast' => GNTDiagramBlastJob::class,
    'gnt_diagram_fasta' => GNTDiagramFastaJob::class,
    'gnt_diagram_sequence_id' => GNTDiagramSequenceIdJob::class,
    'gnt_view_diagram' => GNTViewDiagramJob::class,
    'cgfp_identify' => CGFPIdentifyJob::class,
    'cgfp_quantify' => CGFPQuantifyJob::class,
    'taxonomy_accession' => TaxonomyAccessionJob::class,
    'taxonomy_families' => TaxonomyFamiliesJob::class,
    'taxonomy_fasta' => TaxonomyFastaJob::class,
])]
abstract class Job
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::GUID)]
    private ?string $uuid = null;

    #[ORM\ManyToOne(inversedBy: 'jobs')]
    private ?UserEntity $user = null;

    #[ORM\Column(length: 255, enumType: JobStatus::class)]
    private ?JobStatus $status = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $timeCreated = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $timeStarted = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $timeCompleted = null;

    #[ORM\Column(name: 'efi_db_version', length: 255, nullable: true)]
    private ?string $dbVersion = null;

    #[ORM\ManyToOne(targetEntity: DatabaseMetadata::class)]
    #[ORM\JoinColumn(name: 'db_metadata_id', nullable: true)]
    private ?DatabaseMetadata $dbMetadata = null; // Used by Symfony Form

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $jobName = null;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'childJobs')]
    private ?self $parentJob = null;

    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'parentJob')]
    private Collection $childJobs;

    #[ORM\Column]
    private ?bool $isPublic = false;

	#[ORM\Column(nullable: true)]
    private ?bool $isExample = false;

    #[ORM\Column(nullable: true)]
    private ?int $schedulerJobId = null;

    // Doesn't map to a database column, used internally, override in subclasses
    protected SummaryJobType $summaryJobType;

    public function __construct()
    {
        $this->childJobs = new ArrayCollection();
        $this->uuid = \Symfony\Component\Uid\Uuid::v6();
        $this->status = JobStatus::New;
        $this->timeCreated = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUuid(): ?string
    {
        return $this->uuid;
    }

    public function setUuid(string $uuid): static
    {
        $this->uuid = $uuid;

        return $this;
    }

    public function getUser(): ?UserEntity
    {
        return $this->user;
    }

    public function setUser(?UserEntity $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getStatus(): ?JobStatus
    {
        return $this->status;
    }

    public function setStatus(JobStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getTimeCreated(): ?\DateTimeInterface
    {
        return $this->timeCreated;
    }

    public function setTimeCreated(?\DateTimeInterface $timeCreated): static
    {
        $this->timeCreated = $timeCreated;

        return $this;
    }

    public function getTimeStarted(): ?\DateTimeInterface
    {
        return $this->timeStarted;
    }

    public function setTimeStarted(?\DateTimeInterface $timeStarted): static
    {
        $this->timeStarted = $timeStarted;

        return $this;
    }

    public function getTimeCompleted(): ?\DateTimeInterface
    {
        return $this->timeCompleted;
    }

    public function setTimeCompleted(?\DateTimeInterface $timeCompleted): static
    {
        $this->timeCompleted = $timeCompleted;

        return $this;
    }

    public function getDbVersion(): ?string
    {
        return $this->dbVersion;
    }

    public function setDbVersion(?string $dbVersion): static
    {
        $this->dbVersion = $dbVersion;

        return $this;
    }

    public function getDbMetadata(): ?DatabaseMetadata
    {
        return $this->dbMetadata;
    }

    public function setDbMetadata(?DatabaseMetadata $dbMetadata): static
    {
        $this->dbMetadata = $dbMetadata;

        return $this;
    }

    public function getParentJob(): ?self
    {
        return $this->parentJob;
    }

    public function setParentJob(?self $parentJob): static
    {
        $this->parentJob = $parentJob;

        return $this;
    }

    public function getChildJobs(): Collection
    {
        return $this->childJobs;
    }

    public function addChildJob(self $childJob): static
    {
        if (!$this->childJobs->contains($childJob)) {
            $this->childJobs->add($childJob);
            $childJob->setParentJob($this);
        }

        return $this;
    }

    public function removeChildJob(self $childJob): static
    {
        if ($this->childJobs->removeElement($childJob)) {
            // set the owning side to null (unless already changed)
            if ($childJob->getParentJob() === $this) {
                $childJob->setParentJob(null);
            }
        }

        return $this;
    }

    public function isPublic(): ?bool
    {
        return $this->isPublic;
    }

    public function setIsPublic(bool $isPublic): static
    {
        $this->isPublic = $isPublic;

        return $this;
    }

    public function getIsExample(): ?int
    {
        return $this->isExample;
    }

    public function setIsExample(int $isExample): static
    {
        $this->isExample = $isExample;

        return $this;
    }

    public function getSchedulerJobId(): ?int
    {
        return $this->schedulerJobId;
    }

    public function setSchedulerJobId(int $schedulerJobId): static
    {
        $this->schedulerJobId = $schedulerJobId;

        return $this;
    }

    public function getJobName(): ?string
    {
        return $this->jobName;
    }

    public function setJobName(?string $jobName): static
    {
        $this->jobName = $jobName;

        return $this;
    }



    /**
     * Calculates the time period between start and finish.
     * Returns a DateInterval object or null if not applicable.
     */
    public function getTimePeriodInterval(): ?\DateInterval
    {
        if ($this->timeStarted && $this->timeCompleted && $this->timeCompleted >= $this->timeStarted) {
            return $this->timeCompleted->diff($this->timeStarted);
        }
        return null;
    }

    /**
     * Returns a human-readable string for the time period.
     * Example: "1h 2min 30s", "N/A", "Pending"
     */
    public function getFormattedTimePeriod(): string
    {
        $interval = $this->getTimePeriodInterval();

        if ($interval) {
            $parts = [];
            if ($interval->y) $parts[] = $interval->y . ' year' . ($interval->y > 1 ? 's' : '');
            if ($interval->m) $parts[] = $interval->m . ' month' . ($interval->m > 1 ? 's' : '');
            if ($interval->d) $parts[] = $interval->d . ' day' . ($interval->d > 1 ? 's' : '');
            if ($interval->h) $parts[] = $interval->h . ' hour' . ($interval->h > 1 ? 's' : '');
            if ($interval->i) $parts[] = $interval->i . ' minute' . ($interval->i > 1 ? 's' : '');
            // Always show seconds if no other parts, or if it's non-zero
            if ($interval->s || empty($parts)) {
                 $parts[] = $interval->s . ' second' . ($interval->s > 1 || $interval->s === 0 ? 's' : '');
            }
            
            return empty($parts) ? '0 seconds' : implode(', ', $parts);
        }

        if (!$this->timeStarted) {
            return 'Not started';
        }
        if (!$this->timeCompleted) {
            return 'In progress';
        }
        if ($this->timeCompleted < $this->timeStarted) {
            return 'Invalid date range'; // Should ideally not happen
        }

        return 'N/A'; // Default fallback
    }

    public function getFormattedType(): string
    {
        return DisplayName::getFrom($this);
    }

    public function getSummaryJobType(): SummaryJobType
    {
        return $this->summaryJobType;
    }

    public function getShortClassName(): string
    {
        return (new \ReflectionClass($this))->getShortName();
    }

    // Virtual property to help job summary service work
    public function getFormattedStatus(): string
    {
        if ($this->status === null) {
            return 'Unknown';
        }
        return ucfirst($this->status->value);
    }

    // Virtual property to help job summary service work
    public function getResultsLink(): ?string
    {
        return 'link';
    }
}
