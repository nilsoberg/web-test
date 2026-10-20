<?php

namespace App\ViewModel\ClusterAnalysisResults;

use App\Enum\SequenceType;
use App\ViewModel\ClusterAnalysisResults\LengthHistogramViewModel;

final readonly class JobLengthHistogramsViewModel
{
    public function __construct(
        public string $clusterId,
        public string $clusterName,
        public ?int $numUniprotIds,
        public ?int $numUniref90Ids,
        public ?int $numUniref50Ids,
        public ?LengthHistogramViewModel $uniprot,
        public ?LengthHistogramViewModel $uniprotDomain,
        public ?LengthHistogramViewModel $uniref90,
        public ?LengthHistogramViewModel $uniref90Domain,
        public ?LengthHistogramViewModel $uniref50,
        public ?LengthHistogramViewModel $uniref50Domain,
    )
    {
    }
}
