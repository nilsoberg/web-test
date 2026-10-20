<?php

namespace App\ViewModel;

final readonly class ESTSSNFinalizationViewModel
{
    public function __construct(
        public array $repnodeNetworks,
        public NetworkFileViewModel $fullNetwork,
        public array $summaryData,
        public string $summaryDownloadUrl,
        public array $parentSummaryData,
        public string $parentSummaryDownloadUrl,
        public int $id,
        public string $key,
        public string $jobName,
        public array $sunburstParams,
        public string $parentJobType,
    )
    {
    }
}
