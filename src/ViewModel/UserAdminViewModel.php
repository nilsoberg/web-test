<?php

namespace App\ViewModel;

final readonly class UserAdminViewModel
{
    public function __construct(
        public array $users,
        public bool $isSuperAdmin
    )
    {
    }
}
