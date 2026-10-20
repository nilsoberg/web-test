<?php

namespace App\Entity;

use App\Enum\DomainRegion;
use Doctrine\ORM\Mapping as ORM;

trait DomainBoundariesTrait
{
    #[ORM\Column(nullable: true)]
    private ?bool $domain = false;

    #[ORM\Column(enumType: DomainRegion::class)]
    private ?DomainRegion $domainRegion = DomainRegion::Domain;

    public function getDomain(): ?bool
    {
        return $this->domain;
    }

    public function setDomain(?bool $domain): static
    {
        $this->domain = $domain;

        return $this;
    }

    public function getDomainRegion(): ?DomainRegion
    {
        return $this->domainRegion;
    }

    public function setDomainRegion(?DomainRegion $domainRegion): static
    {
        $this->domainRegion = $domainRegion;

        return $this;
    }
    
    public function getFormattedDomain(): string
    {
        if ($this->domain)
        {
            if ($this->domainRegion === DomainRegion::NTerminal)
            {
                return "on (N-terminal)";
            }
            else if ($this->domainRegion === DomainRegion::CTerminal)
            {
                return "on (C-terminal)";
            }
            else
            {
                return "on";
            }
        }
        else
        {
            return false;
        }
    }
}
