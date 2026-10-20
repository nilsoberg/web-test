<?php

namespace App\ViewModel;

use App\Enum\SequenceType;

final readonly class ESTGenerateResultsViewModel
{
    public function __construct(
        public string $jobType,
        public string $jobName,
        public int $id,
        public string $key,
        public bool $isDomain,
        public bool $useFamilies,
        public string $unirefVersion,
        public array $summaryData,
        public string $summaryDownloadUrl,
        public array $resultImages,
        public array $resultPlots,
        public array $createdSsns,
        public string $edgesTabUrl,
        public array $sunburstParams,
    )
    {
    }
}
