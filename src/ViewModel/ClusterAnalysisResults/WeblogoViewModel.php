<?php

namespace App\ViewModel\ClusterAnalysisResults;

final readonly class WeblogoViewModel
{
    public function __construct(
        public string $imagePath,
        public string $imageDownloadUrl,
        public string $msaDownloadUrl,
    )
    {
    }
}
