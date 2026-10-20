<?php

namespace App\Enum;

enum SummaryJobType: string {
    case EST = 'EST';
    case GNT = 'GNT';
    case GND = 'GND';
    case CGFP = 'CGFP';
    case TAXONOMY = 'TAXONOMY';
}
