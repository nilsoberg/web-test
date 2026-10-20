<?php

namespace App\ViewModel\ClusterAnalysisResults;

final readonly class ConsensusResidueViewModel
{
    public function __construct(
        public string $residue,
        public string $downloadUrl,
        public string $downloadSize,
    )
    {
    }
}
