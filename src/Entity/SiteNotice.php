<?php

namespace App\Entity;

use App\Repository\SiteNoticeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SiteNoticeRepository::class)]
class SiteNotice
{
    public const TYPE_NOTICE = 'notice';
    public const TYPE_ALERT = 'alert';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $message = null;

    #[ORM\Column]
    private ?bool $isActive = false;

    #[ORM\Column(length: 20)]
    private string $type = self::TYPE_NOTICE;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $activatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    
    }
    public function getMessage(): ?string
    {
        return $this->message;
    }
    
    public function setMessage(string $message): static
    {
        $this->message = $message; return $this;
    }
    
    public function isActive(): ?bool
    {
        return $this->isActive;
    }
    
    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }
    
    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
    
    public function getActivatedAt(): ?\DateTimeImmutable
    {
        return $this->activatedAt;
    }
    
    public function setActivatedAt(?\DateTimeImmutable $activatedAt): static
    {
        $this->activatedAt = $activatedAt;
        
        return $this;
    }
}
