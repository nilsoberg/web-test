<?php

namespace App\Dto;

class CGFPHeatmapAbundance
{
    public function __construct(
        public array $clusterData,
        public array $metagenomes,
        public array $siteInfo,
        public array $bodySites,
        public int $minExtent,
        public int $maxExtent,
    )
    {
    }
}
