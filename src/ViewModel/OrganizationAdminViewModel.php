<?php

namespace App\ViewModel;

final readonly class OrganizationAdminViewModel
{
    public function __construct(
        public array $organizations
    )
    {
    }
}
