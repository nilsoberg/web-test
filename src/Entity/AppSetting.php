<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class AppSetting
{
    #[ORM\Id]
    #[ORM\Column(length: 255)]
    private ?string $settingKey = null; // e.g., 'global_default_database', 'site_title'

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $settingValue = null;

    public function getSettingKey(): ?string
    {
        return $this->settingKey;
    }
    
    public function setSettingKey(string $settingKey): static
    {
        $this->settingKey = $settingKey;
        
        return $this;
    }

    public function getSettingValue(): ?string
    {
        return $this->settingValue;
    }

    public function setSettingValue(?string $settingValue): static
    {
        $this->settingValue = $settingValue;
        
        return $this;
    }
}
