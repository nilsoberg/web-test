<?php

namespace App\ViewModel\ClusterAnalysisResults;

final readonly class LengthHistogramViewModel
{
    public function __construct(
        public string $imagePath,
        public string $imageDownloadUrl,
    )
    {
    }
}
