<?php

namespace App\ViewModel;

final readonly class CGFPQuantifyResultsViewModel
{
    public function __construct(
        public NetworkFileViewModel $ssn,
        public ?NetworkFileViewModel $cdhitDownload,
        public ?NetworkFileViewModel $markerDownload,
        public ?NetworkFileViewModel $metagenomeDesc,
        public CGFPQuantifyAbundanceViewModel $abundanceFiles,
        public string $clusterHeatmapUrl,
        public string $singletonHeatmapUrl,
        public string $mergedHeatmapUrl,
        public string $clusterBoxplotUrl,
        public string $singletonBoxplotUrl,
        public string $mergedBoxplotUrl,
        public array $summaryData,
        public string $summaryDownloadUrl,
        public int $id,
        public string $key,
        public string $jobName,
    )
    {
    }
}
