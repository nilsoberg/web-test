<?php

namespace App\ViewModel;

final readonly class ESTConvergenceRatioViewModel
{
    public function __construct(
        public string $jobName,
        public array $summaryData,
        public string $summaryDownloadUrl,
        public array $files,
    )
    {
    }
}
