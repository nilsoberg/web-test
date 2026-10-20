<?php

namespace App\ViewModel;

use App\Entity\Job;

final class JobAdminViewModel
{
    public function __construct(
        public string $type,
        public int $id,
        public ?string $name,
        public \DateTimeInterface $timeCreated,
        public ?\DateTimeInterface $timeStarted,
        public ?\DateTimeInterface $timeCompleted,
        public string $status,
        public string $statusBadgeClass,
        public string $userEmail,
        public string $userFullName,
        public ?string $userOrganization,
        public ?string $viewRoute,
    )
    {
    }
}
