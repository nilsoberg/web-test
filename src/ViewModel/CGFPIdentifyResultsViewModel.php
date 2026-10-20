<?php

namespace App\ViewModel;

final readonly class CGFPIdentifyResultsViewModel
{
    public function __construct(
        public ?NetworkFileViewModel $ssn,
        public ?NetworkFileViewModel $cdhitDownload,
        public ?NetworkFileViewModel $markerDownload,
        public array $summaryData,
        public string $summaryDownloadUrl,
        public int $id,
        public string $key,
        public string $jobName,
    )
    {
    }
}
