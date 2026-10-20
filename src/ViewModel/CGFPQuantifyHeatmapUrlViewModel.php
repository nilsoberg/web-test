<?php

namespace App\ViewModel;

use App\Enum\CGFPHeatmapType;
use App\Enum\CGFPSearchType;
use App\Enum\SequenceType;

final readonly class CGFPQuantifyHeatmapUrlViewModel
{
    public function __construct(
        public int $id,
        public string $key,
        public string $jobName,
        public int $identifyId,
        public string $heatmapType,
        public ?int $width = null,
        public ?string $searchType = null,
        public ?string $refDb = null,
        public ?string $searchSens = null,
        public ?int $cdhitSequenceIdentity = null,
    )
    {
    }
}
