<?php

namespace App\Enum;

enum CGFPHeatmapType : string
{
    case Cluster = 'cluster';
    case Singleton = 'singleton';
    case Merged = 'merged';
}
