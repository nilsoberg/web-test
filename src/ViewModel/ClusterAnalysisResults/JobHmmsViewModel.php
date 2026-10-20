<?php

namespace App\ViewModel\ClusterAnalysisResults;

use App\Enum\SequenceType;

final readonly class JobHmmsViewModel
{
    public function __construct(
        public array $hmms,
        public string $zipDownloadUrl,
        public string $zipDownloadSize,
    )
    {
    }
}
