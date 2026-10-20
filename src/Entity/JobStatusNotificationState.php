<?php

namespace App\Entity;

use App\Repository\JobStatusNotificationStateRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: JobStatusNotificationStateRepository::class)]
class JobStatusNotificationState
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Job $job = null;

    #[ORM\Column(length: 255)]
    private ?string $lastNotifiedStatus = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $lastNotifiedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getJob(): ?Job
    {
        return $this->job;
    }

    public function setJob(Job $job): static
    {
        $this->job = $job;

        return $this;
    }

    public function getLastNotifiedStatus(): ?string
    {
        return $this->lastNotifiedStatus;
    }

    public function setLastNotifiedStatus(string $lastNotifiedStatus): static
    {
        $this->lastNotifiedStatus = $lastNotifiedStatus;

        return $this;
    }

    public function getLastNotifiedAt(): ?\DateTimeImmutable
    {
        return $this->lastNotifiedAt;
    }

    public function setLastNotifiedAt(\DateTimeImmutable $lastNotifiedAt): static
    {
        $this->lastNotifiedAt = $lastNotifiedAt;

        return $this;
    }
}
