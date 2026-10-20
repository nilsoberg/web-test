<?php

namespace App\ViewModel\ClusterAnalysisResults;

use App\Enum\SequenceType;

final readonly class JobWeblogosViewModel
{
    public function __construct(
        public array $weblogos,
        public string $zipDownloadUrl,
        public string $zipDownloadSize,
        public string $pimZipDownloadUrl,
        public string $pimZipDownloadSize,
    )
    {
    }
}
