<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class PublicUserCreationData
{
    #[Assert\NotBlank]
    public ?string $fullName = null;

    #[Assert\NotBlank]
    #[Assert\Email]
    public ?string $email = null;

    public ?string $timezone = null;
}
