<?php

namespace App\ViewModel;

final readonly class GNTGNNViewModel
{
    public function __construct(
        public string $jobName,
        public array $summaryData,
        public string $summaryDownloadUrl,
        public NetworkFileViewModel $colorSsn,
        public NetworkFileViewModel $ssnGnn,
        public NetworkFileViewModel $pfamGnn,
        public array $otherFiles,
        public string $gndDownloadRoute,
        public string $gndDownloadSize,
        public string $gndViewerRoute
    )
    {
    }
}
