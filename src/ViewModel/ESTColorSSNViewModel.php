<?php

namespace App\ViewModel;

final readonly class ESTColorSSNViewModel
{
    public function __construct(
        public string $jobName,
        public array $summaryData,
        public string $summaryDownloadUrl,
        public array $files,
        public string $ssnDownloadRoute,
        public string $displaySsnFileSize,
        public ?string $convergenceRatioRoute,
        public ?string $cgfpRoute 
    )
    {
    }
}
