<?php

namespace App\ViewModel;

final readonly class TaxonomyResultsViewModel
{
    public function __construct(
        public int $id,
        public string $jobType,
        public string $key,
        public string $jobName,
        public array $summaryData,
        public string $summaryDownloadUrl,
        public array $resultImages,
        public array $sunburstParams
    )
    {
    }
}
