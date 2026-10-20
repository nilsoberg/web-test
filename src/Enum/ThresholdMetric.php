<?php

namespace App\Enum;

enum ThresholdMetric: string {
    case AlignmentScore = 'alignment_score';
    case PercentIdentity = 'pident';
    case Bitscore = 'bitscore';

    public function getLabel(): string
    {
        return match($this) {
            self::AlignmentScore => 'Alignment Score',
            self::PercentIdentity => 'Percent Identity',
            self::Bitscore => 'Bitscore',
        };
    }
}
