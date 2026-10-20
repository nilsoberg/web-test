<?php

namespace App\ViewModel;

use App\Enum\SequenceType;

final readonly class ClusterAnalysisResultsViewModel
{
    public function __construct(
        public string $jobName,
        public array $summaryData,
        public string $summaryDownloadUrl,
        public array $files,
        public ?string $ssnDownloadRoute,
        public ?string $displaySsnFileSize,
        public ?string $convergenceRatioRoute,
        public ?string $cgfpRoute,

        public ClusterAnalysisResults\JobHmmsViewModel $hmms,
        /** @var {JobLengthHistogramsViewModel[]} $lengthHistograms - array of JobLengthHistogramsViewModel */
        public array $lengthHistograms,
        public ClusterAnalysisResults\JobWeblogosViewModel $weblogos,

        /** @var ConsensusResidueViewModel[] $consensusResidues */
        public array $consensusResidues,

        public bool $isDomain,
    )
    {
    }
}
