<?php

namespace App\ViewModel;

final readonly class SuperadminViewModel
{
    public function __construct(
        public array $userData
    )
    {
    }
}
