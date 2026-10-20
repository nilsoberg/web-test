<?php

namespace App\ViewModel\Factory;

use App\Enum\JobSummarySortOrder;
use App\Enum\SummaryJobType;
use App\Repository\JobRepository;
use App\Service\DatabaseListService;
use App\Service\UserJobsSummaryService;
use App\ViewModel\HomePageViewModel;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\Security\Core\User\UserInterface; 

class HomePageViewModelFactory
{
    public function __construct(
        private readonly DatabaseListService $databaseService,
        private readonly Security $security,
        private readonly UserJobsSummaryService $summaryService,
    )
    {
    }

    public function create(SummaryJobType $jobType, ?InputBag $query = null): HomePageViewModel
    {
        $currentUser = $this->security->getUser();
        $isLoggedIn = $currentUser !== null;

        $sortByAllowed = [
            'id' => JobSummarySortOrder::ID,
            'dcr' => JobSummarySortOrder::DateCreated,
            'dco' => JobSummarySortOrder::DateCompleted,
        ];
        $sortBy = $query?->get('sb');

        if ($sortBy === null || !isset($sortByAllowed[$sortBy])) {
            $sortBy = 'dco';
        }
        $sortDir = $query?->get('dir') ?? '' === 'rev' ? 'rev' : '';
        $sortAsc = $sortDir === 'rev' ? true : false;

        $userJobs = $this->summaryService->getJobsForDisplay($jobType, $sortByAllowed[$sortBy], $sortAsc);
        if ($userJobs === null) {
            $userJobs = [];
        }

        $defaultDatabaseId = $this->databaseService->getDefaultDatabase()?->getId();

        $email = $currentUser?->getEmail() ?? '';

        $viewModel = new HomePageViewModel(
            userJobs: $userJobs,
            isLoggedIn: $isLoggedIn,
            email: $email,
            dashboardSortBy: $sortBy,
            dashboardSortDir: $sortDir,
            defaultDatabaseId: $defaultDatabaseId,
        );

        return $viewModel;
    }
}
