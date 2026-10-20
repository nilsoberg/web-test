<?php

namespace App\ViewModel\ClusterAnalysisResults;

final readonly class GroupHmmViewModel
{
    public function __construct(
        public string $clusterId,
        public string $clusterName,
        public ?int $numUniprotIds,
        public ?int $numUniref90Ids,
        public ?int $numUniref50Ids,
        public ?HmmViewModel $full,
        public ?HmmViewModel $domain,
    )
    {
    }
}
