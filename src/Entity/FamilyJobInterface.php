<?php

namespace App\Entity;

use App\Enum\SequenceType;

interface FamilyJobInterface
{
    public function setAddFamilies(?string $families): static;
    public function getAddFamilies(): ?string;
    public function setUnirefVersion(?SequenceType $unirefVersion): static;
    public function getUnirefVersion(): ?SequenceType;
    public function setFraction(?int $fraction): static;
    public function getFraction(): ?int;
}
