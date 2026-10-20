<?php

namespace App\ViewModel;

use App\Entity\Job;

final class JobViewModel
{
    public ?array $children = null;
    public ?string $color = null;
    public ?array $metadata = null;
    public ?string $prettyStatus = null;
    public ?string $route = null;
    public ?string $jobName = null;
    public ?string $badgeStatus = null;
    public ?string $badgeIcon = null;
    public ?string $actionIcon = null;
    public ?string $actionStyle = null;
    public ?string $actionUrl = null;
    public ?string $actionTooltip = null;

    public function __construct(
        public readonly Job $job,
    )
    {
    }
}
