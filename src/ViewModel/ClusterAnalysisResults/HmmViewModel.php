<?php

namespace App\ViewModel\ClusterAnalysisResults;

final readonly class HmmViewModel
{
    public function __construct(
        public ?string $imagePath,
        public ?string $imageDownloadUrl,
        public string $skylignViewerUrl,
        public string $hmmDownloadUrl,
    )
    {
    }
}
