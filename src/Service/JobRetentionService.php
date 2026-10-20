<?php

namespace App\Service;

use DateTime;
use Symfony\Component\Security\Core\User\UserInterface;

class JobRetentionService
{
    public function __construct(
        private readonly int $retentionDays,
    )
    {
    }

    public function getJobExpirationDate(UserInterface $user): DateTime
    {
        //TODO: return a conditional expiration date depending on the user
        $numDays = $this->retentionDays;
        return new DateTime('-' . $numDays . ' days');
    }

    public function getRetentionDays(?UserInterface $user = null): int
    {
        return $this->retentionDays;
    }
}
