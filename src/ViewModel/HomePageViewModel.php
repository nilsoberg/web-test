<?php

namespace App\ViewModel;

// View model for the entry point for every tool, containing the list of users jobs, primarily
final class HomePageViewModel
{
    public function __construct(
        public array $userJobs,
        public bool $isLoggedIn,
        public string $email,
        public string $activeForm = "",
        public string $dashboardSortBy = "",
        public string $dashboardSortDir = "",
        public ?int $defaultDatabaseId = null,
    )
    {
    }
}
