<?php

namespace App\Enum;

enum AllByAllSearchType: string
{
    case Blast = 'blast';
    case BlastPlus = 'blast+';
    case Diamond = 'diamond';
    case MMSeqs2 = 'mmseqs2';

    public function getLabel(): string
    {
        return match($this) {
            self::Blast => 'BLAST',
            self::BlastPlus => 'BLAST+',
            self::Diamond => 'DIAMOND',
            self::MMSeqs2 => 'MMSeqs2',
        };
    }
}
